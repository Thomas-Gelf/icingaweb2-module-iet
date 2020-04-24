<?php

namespace Icinga\Module\Iet\ImportSource;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Iet\Config;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;

class ImportSourceIetRaw extends ImportSourceHook
{
    protected $api;

    public function getName()
    {
        return 'iET (RAW)';
    }

    /**
     * @return array
     * @throws ConfigurationError
     */
    public function fetchData()
    {
        $api = Config::getApi($this->getSetting('iet_instance'));

        $result = $api->processOperation(
            $this->getSetting('process'),
            $this->getSetting('xml'),
            $this->getSetting('version')
        );

        $key = $this->getSetting('result_key');
        if (\strlen($key)) {
            if (isset($result->$key)) {
                $result = $result->$key;
            } else {
                throw new InvalidArgumentException("There is no '$key' in the result");
            }
        }

        switch ($this->getSetting('result_type')) {
            case 'keyValueString':
                return $this->parseKeyValueString($result);
            case 'objectList':
                return $this->fixObjectList($result);
            default:
                throw new RuntimeException(\sprintf(
                    'Object list expected, got "%s"',
                    (string) $result->asXML()
                ));
        }
    }

    protected function parseKeyValueString(SimpleXMLElement $result)
    {
        $data = [];
        foreach ($result as $element) {
            $parts = \preg_split('/,/', $element, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                if (\preg_match('/^(.+)=(.*)$/', $part, $match)) {
                    $data[$match[1]] = $match[2];
                } else {
                    throw new InvalidArgumentException(\sprintf(
                        'Unable to parse "%s" in "%s"',
                        $part,
                        (string) $result->asXML()
                    ));
                }
            }
        }

        return $data;
    }

    protected function fixObjectList(SimpleXMLElement $result)
    {
        $data = [];
        foreach ($result as $element) {
            $data[] = (object) \get_object_vars($element);
        }

        return $data;
    }

    public function listColumns()
    {
        $columns = [];

        foreach ($this->fetchData() as $object) {
            foreach (\array_keys((array) $object) as $column) {
                if (! isset($columns[$column])) {
                    $columns[$column] = true;
                }
            }
        }

        return \array_keys($columns);
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        try {
            $ietInstances = Config::enumInstances();
            $form->addElement('select', 'iet_instance', [
                'label' => $form->translate('iET Instance'),
                'multiOptions' => $ietInstances,
                'required' => true,
                'class'    => 'autosubmit',
                'ignore'   => true,
            ]);

            $api = Config::getApi($form->getSentOrObjectSetting('iet_instance'));
        } catch (ConfigurationError $e) {
            $form->addException($e, 'iet_instance');

            return;
        }

        $form->addElement('text', 'process', array(
            'label'       => $form->translate('Service Name'),
            'description' => $form->translate(
                'Webservice Name (the "process" passed to ProcessOperation)'
            ),
            'required'    => true,
        ));

        $form->addElement('textarea', 'xml', [
            'label'       => $form->translate('ProcessData XML'),
            'description' => $form->translate(
                'This XML will be placed into the processData tag of the ProcessOperation method'
            ),
            'rows'        => 10,
            'required'    => true,
        ]);

        $form->addElement('text', 'version', [
            'label'       => $form->translate('Version'),
            'description' => $form->translate(
                'Your webservice process version (defaults to 1.0)'
            ),
            'value'       => '1.0',
            'required'    => true,
        ]);

        $form->addElement('text', 'result_key', [
            'label'       => $form->translate('Result Key'),
            'description' => $form->translate(
                'Which property to pick from the result (e.g. "Result", "contents")'
            ),
        ]);

        $form->addElement('select', 'result_type', [
            'label'       => $form->translate('Result Data Type'),
            'description' => $form->translate(
                'What kind of data is going to be returned?'
            ),
            'multiOptions' => [
                'keyValueString' => $form->translate('Comma-separated key=value string'),
                'objectList'     => $form->translate('A list of objects'),
            ],
            'required' => true,
        ]);

        return;
    }
}
