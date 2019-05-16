<?php

namespace Icinga\Module\Iet\Web\Form;

use dipl\Translation\TranslationHelper;
use Exception;
use Icinga\Application\Logger;
use Icinga\Authentication\Auth;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\IcingaCommandPipe;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;

class CreateOperationalRequestForEventConsoleForm extends QuickForm
{
    use TranslationHelper;

    /** @@var \Icinga\Module\Iet\Api */
    private $api;

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        try {
            $ietInstances = Config::enumInstances();
            $this->addElement('select', 'iet_instance', [
                'label' => $this->translate('iET Instance'),
                'multiOptions' => $ietInstances,
                'required' => true,
                'ignore'   => true,
            ]);

            if ($this->hasBeenSent()) {
                $this->api = Config::getApi($this->getSentValue('iet_instance'));
            } else {
                $this->api = Config::getApi();
            }
        } catch (ConfigurationError $e) {
            $this->addException($e);
            $this->submitLabel = false;

            return;
        }

        $myUsername = Auth::getInstance()->getUser()->getUserName();
        // $reporters = $this->api->listReporters();
        // $this->prefixEnumValueWithName($reporters);
        $groups = $this->api->listGroupsAssignable();
        $this->prefixEnumValueWithName($groups);
        $sourceSystems = $this->api->listSourceSystems();

        $this->addElement('select', 'sourcesystemid', [
            'label' => $this->translate('Source System'),
            'multiOptions' => $this->optionalEnum($sourceSystems),
            'required' => true,
        ]);
        $this->addElement('select', 'repgrp', [
            'label' => $this->translate('Group (Reporter)'),
            'multiOptions' => $this->optionalEnum($groups),
            'required' => true,
        ]);
        $this->addElement('text', 'rep', [
            'label' => $this->translate('Reporter'),
            // 'multiOptions' => $this->optionalEnum($reporters),
            'value' => $myUsername,
            'required' => true,
        ]);
        $this->addElement('text', 'caller', [
            'label' => $this->translate('Caller'),
            // 'multiOptions' => $this->optionalEnum($reporters),
            'value' => $myUsername,
            'required' => true,
        ]);
        $this->addElement('select', 'fe', [
            'label' => $this->translate('FE'),
            'multiOptions' => $this->optionalEnum($groups),
            'required' => true,
        ]);

        $this->addElement('text', 'title1', [
            'label'       => $this->translate('Title'),
            'required'    => true,
            'value'       => $this->getObjectDefault('title'),
            'description' => $this->translate(
                'Summary of this incident'
            ),
        ]);

        $this->addElement('textarea', 'details', array(
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => $this->getObjectDefault('details'),
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ));
    }

    protected function prefixEnumValueWithName(& $enum)
    {
        foreach ($enum as $name => $value) {
            $enum[$name] = "$name: $value";
        }
    }

    private function getObjectDefault($key)
    {
        $defaults = $this->getObjectDefaults();
        if (array_key_exists($key, $defaults)) {
            return $defaults[$key];
        } else {
            return null;
        }
    }

    public function setObject(MonitoredObject $object)
    {
        $this->object = $object;

        return $this;
    }

    private function getObjectDefaults()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            $description = $object->service_output;
            $summary = sprintf(
                '%s on %s is %s',
                $object->service_description,
                $object->host_name,
                $this->getStateName()
            );
        } else {
            $description = $object->host_output;
            $summary = sprintf(
                '%s is %s',
                $object->host_name,
                $this->getStateName()
            );
        }

        $defaults = [
            'title'     => $summary,
            'details' => $description,
        ];

        return $defaults;
    }

    protected function getStateName()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            return strtoupper(Service::getStateText($object->service_state));
        } else {
            return strtoupper(Host::getStateText($object->host_state));
        }
    }

    public function onSuccess()
    {
        $key = $this->createOperationalRequest();
        $this->setSuccessMessage("New Operational Request $key has been created");
        parent::onSuccess();
    }

    private function createOperationalRequest()
    {
        $object = $this->object;
        $host = $object->host_name;
        if ($object->getType() === 'service') {
            $service = $object->service_description;
        } else {
            $service = null;
        }

        $key = $this->api->createOR($this->getValues());
        $instance = $this->getValue('iet_instance');
        $ackMessage = "Operational Request $instance:$key has been created";

        try {
            $cmd = new IcingaCommandPipe();
            if ($cmd->acknowledge("iET ($instance)", $ackMessage, $host, $service)) {
                Logger::info("Problem has been acknowledged for $key");
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            $this->notifyError($e->getMessage());
        }

        return $key;
    }

    protected function makeEnum($data, $key, $name, $reject = null)
    {
        $enum = [];
        foreach ($data as $entry) {
            if (is_callable($reject) && $reject($entry)) {
                continue;
            }
            $value = $this->getProperty($entry, $key);
            $caption = $this->getProperty($entry, $name, $value);
            $enum[$value] = $caption;
        }

        return $enum;
    }

    protected function getProperty($entry, $name, $default = null)
    {
        if (property_exists($entry, $name)) {
            $value = $entry->$name;
        }

        if (empty($value)) {
            return $default;
        } else {
            return $value;
        }
    }
}
