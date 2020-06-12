<?php

namespace Icinga\Module\Iet\Web\Form;

use Exception;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\Config as WebConfig;
use Icinga\Application\Logger;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\IcingaCommandPipe;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Notification;
use ipl\Html\Form;
use ipl\Html\FormDecorator\DdDtDecorator;

abstract class BaseMonitoringTicketForm extends Form
{
    use TranslationHelper;

    /** @@var \Icinga\Module\Iet\Api */
    protected $api;

    /** @var  MonitoredObject */
    private $object;

    protected $ietProcessName;

    protected $ietProcessVersion = '1.0';

    public function __construct(MonitoredObject $object)
    {
        $this->object = $object;
        $this->setDefaultElementDecorator(new DdDtDecorator());
    }

    abstract protected function addMessageDetails();

    protected function getIetProcessName()
    {
        if ($this->ietProcessName === null) {
            throw new \RuntimeException(
                'Configuration/implementation error, ietProcessName is required'
            );
        }

        return $this->ietProcessName;
    }

    protected function getIetProcessVersion()
    {
        return $this->ietProcessVersion;
    }

    protected function assemble()
    {
        $this->api = $this->askForApiInstance();
        if ($this->api === null) {
            return;
        }
        $this->addMessageDetails();
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Create')
        ]);
    }

    /**
     * @return \Icinga\Module\Iet\Api|null
     */
    protected function askForApiInstance()
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
                return Config::getApi($this->getElement('iet_instance')->getValue());
            } else {
                return Config::getApi();
            }
        } catch (ConfigurationError $e) {
            $this->getElement('iet_instance')->addMessage($e->getMessage());

            return null;
        }
    }

    /**
     * Helper method, get a default value according an eventually configured pattern
     *
     * @param $property
     * @param null $enum
     * @param null $default
     * @return string|null
     */
    protected function getDefaultFromConfig($property, $enum = null, $default = null)
    {
        $setting = WebConfig::module('iet')->get('defaults', $property);
        if ($setting !== null) {
            if ($enum !== null) {
                if ($idx = \array_search($setting, $enum)) {
                    $setting = $idx;
                } elseif (! \array_key_exists($setting, $enum)) {
                    $setting = null;
                }
            }
        }

        if ($setting === null) {
            return $default;
        } else {
            return $this->fillPlaceholders($setting);
        }
    }

    protected function prefixEnumValueWithName(&$enum)
    {
        foreach ($enum as $name => $value) {
            if ($name !== $value) {
                $enum[$name] = "$name: $value";
            }
        }
    }

    public function onSuccess()
    {
        $key = $this->api->processOperation(
            $this->ietProcessName,
            $this->getValues(),
            $this->ietProcessVersion
        );
        if (isset($key->Result)) {
            // Hint: this is not generic, workaround for special SimpleXML
            $key = (string) $key->Result;
        }
        try {
            $this->ack($key);
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            throw $e;
        }

        $message = "New Ticket $key has been created";
        Notification::success($message);
    }

    protected function optionalEnum($enum)
    {
        return [null => $this->translate('- please choose -')] + $enum;
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

    protected function fillPlaceholders($string)
    {
        return ConfigHelper::fillPlaceholders($string, $this->object);
    }

    protected function getObjectDefault($key)
    {
        $defaults = $this->getObjectDefaults();
        if (\array_key_exists($key, $defaults)) {
            return $defaults[$key];
        } else {
            return null;
        }
    }

    private function getObjectDefaults()
    {
        $object = $this->object;
        $hostName = $object->host_name;
        $stateName = $this->getStateName();
        if ($object->getType() === 'service') {
            $serviceName = $object->service_description;
            $longOutput = $object->service_output;
            $summary = sprintf(
                '%s on %s is %s',
                $serviceName,
                $hostName,
                $stateName
            );
        } else {
            $serviceName = null;
            $longOutput = $object->host_output;
            $summary = sprintf(
                '%s is %s',
                $hostName,
                $stateName
            );
        }

        $defaults = [
            'state'   => $stateName,
            'title'   => $summary,
            'details' => $longOutput,
            'icingahost'    => $hostName,
            'icingaservice' => $serviceName,
        ];

        return $defaults;
    }

    protected function getStateName()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            return \strtoupper(Service::getStateText($object->service_state));
        } else {
            return \strtoupper(Host::getStateText($object->host_state));
        }
    }

    protected function ack($ietKey)
    {
        $object = $this->object;
        $host = $object->host_name;
        if ($object->getType() === 'service') {
            $service = $object->service_description;
        } else {
            $service = null;
        }

        $instance = $this->getValue('iet_instance');
        $ackMessage = "iET issue $instance:$ietKey has been created";

        $cmd = new IcingaCommandPipe();
        if ($cmd->acknowledge("iET ($instance)", $ackMessage, $host, $service)) {
            Logger::info("Problem has been acknowledged for $ietKey");
        }
    }
}
