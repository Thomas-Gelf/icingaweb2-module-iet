<?php

namespace Icinga\Module\Iet\Web\Form;

use Exception;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Application\Logger;
use Icinga\Authentication\Auth;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Iet\IcingaDb\CommandPipe;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\IcingaCommandPipe;
use Icinga\Module\Iet\ObjectHelper;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Web\Notification;
use ipl\Orm\Model;
use RuntimeException;

abstract class BaseMonitoringTicketForm extends Form
{
    use TranslationHelper;

    /** @@var \Icinga\Module\Iet\Api */
    protected $api;

    /** @var ObjectHelper */
    protected $helper;

    /** @var  MonitoredObject|Model */
    private $object;

    protected $ietProcessName;

    protected $ietProcessVersion = '1.0';

    /**
     * @param MonitoredObject|Model $object
     */
    public function __construct($object)
    {
        $this->helper = new ObjectHelper($object);
        $this->object = $object;
    }

    abstract protected function addMessageDetails();

    protected function getIetProcessName()
    {
        if ($this->ietProcessName === null) {
            throw new RuntimeException('Configuration/implementation error, ietProcessName is required');
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
                'value'    => key($ietInstances),
            ]);
            if ($this->hasBeenSent()) {
                return Config::getApi($this->getElement('iet_instance')->getValue());
            }

            return Config::getApi();
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
        return FormUtil::getDefaultFromConfig($this->object, $property);
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
            Notification::error($e->getMessage());
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
        }

        return $value;
    }

    protected function fillPlaceholders($string)
    {
        return ConfigHelper::fillPlaceholders($string, $this->object);
    }

    protected function getObjectDefault($key)
    {
        $defaults = $this->helper->getDefaults();
        if (\array_key_exists($key, $defaults)) {
            return $defaults[$key];
        }

        return null;
    }

    protected function ack($ietKey)
    {
        $helper = $this->helper;
        $host = $helper->getHostName();
        $service = $helper->getServiceName();

        $instance = $this->getValue('iet_instance');
        $ackMessage = "iET ($instance) issue $instance:$ietKey has been created";
        $username = Auth::getInstance()->getUser()->getUsername();

        if ($helper->isIcingaDb()) {
            $cmd = new CommandPipe();
            $acknowledged = $cmd->acknowledgeObject($username, $ackMessage, $this->object);
        } else {
            $cmd = new IcingaCommandPipe();
            $acknowledged = $cmd->acknowledge($username, $ackMessage, $host, $service);
        }
        if ($acknowledged) {
            Logger::info("Problem has been acknowledged for $ietKey");
        }
    }
}
