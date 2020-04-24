<?php

namespace Icinga\Module\Iet\ImportSource;

use Exception;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Iet\Config;

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

        if (\is_string($result)) {
            // For VERY simple APIs
            return [0 => (object) ['stringResult' => $result]];
        } else {
            return $result;
        }
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

        $form->addElement('textarea', 'xml', array(
            'label'       => $form->translate('ProcessData XML'),
            'description' => $form->translate(
                'This XML will be placed into the processData tag of the ProcessOperation method'
            ),
            'required'    => true,
        ));

        $form->addElement('text', 'version', array(
            'label'       => $form->translate('Version'),
            'description' => $form->translate(
                'Your webservice process version (defaults to 1.0)'
            ),
            'value'       => '1.0',
            'required'    => true,
        ));

        return;
    }
}
