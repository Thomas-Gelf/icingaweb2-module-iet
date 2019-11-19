<?php

namespace Icinga\Module\Iet\Web\Form;

use Exception;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\Config as WebConfig;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Authentication\Auth;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Iet\Config;
use Icinga\Web\Notification;
use ipl\Html\Form;
use ipl\Html\FormDecorator\DdDtDecorator;

abstract class BaseOperationalRequestForm extends Form
{
    use TranslationHelper;

    /** @@var \Icinga\Module\Iet\Api */
    private $api;

    private $cacheDir;

    public function __construct()
    {
        $this->setDefaultElementDecorator(new DdDtDecorator());
    }

    abstract protected function addMessageDetails();

    abstract protected function ack($ietKey);

    protected function assemble()
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
                $this->api = Config::getApi($this->getElement('iet_instance')->getValue());
            } else {
                $this->api = Config::getApi();
            }
        } catch (ConfigurationError $e) {
            throw $e;
            $this->addError($e);
            $this->submitLabel = false;

            return;
        }

        $myUsername = Auth::getInstance()->getUser()->getUserName();
        // $reporters = $this->api->listReporters();
        // $this->prefixEnumValueWithName($reporters);
        if (null === ($allGroups = $this->getCached('all-groups'))) {
            $allGroups = $this->api->listGroupsAssignable();
        }
        if (null === ($groups = $this->getCached('my-groups'))) {
            $groups = $this->api->listRepGroups($myUsername);
        }
        $this->prefixEnumValueWithName($allGroups);
        $this->prefixEnumValueWithName($groups);
        if (null === ($sourceSystems = $this->getCached('source-systems'))) {
            $sourceSystems = $this->api->listSourceSystems();
        }

        $defaultSourceSystem = WebConfig::module('iet')->get('defaults', 'sourcesystem');
        if ($defaultSourceSystem) {
            if ($idx = \array_search($defaultSourceSystem, $sourceSystems)) {
                $defaultSourceSystem = $idx;
            } elseif (! \array_key_exists($defaultSourceSystem, $sourceSystems)) {
                $defaultSourceSystem = null;
            }
        }
        $this->addElement('select', 'sourcesystemid', [
            'label'        => $this->translate('Source System'),
            'multiOptions' => $this->optionalEnum($sourceSystems),
            'value'        => $defaultSourceSystem,
            'required'     => true,
        ]);
        $this->addElement('select', 'repgrp', [
            'label' => $this->translate('Group (Reporter)'),
            'multiOptions' => $groups,
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
            'multiOptions' => $this->optionalEnum($allGroups),
            'required' => true,
        ]);

        $this->addMessageDetails();

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Create')
        ]);
    }

    protected function prefixEnumValueWithName(& $enum)
    {
        foreach ($enum as $name => $value) {
            if ($name !== $value) {
                $enum[$name] = "$name: $value";
            }
        }
    }

    public function setSuccessUrl($url)
    {
    }

    public function onSuccess()
    {
        $key = $this->createOperationalRequest();
        $message = "New Operational Request $key has been created";
        Notification::success($message);
    }

    protected function optionalEnum($enum)
    {
        return [null => $this->translate('- please choose -')] + $enum;
    }

    private function createOperationalRequest()
    {
        $key = $this->api->createOR($this->getValues());
        try {
            $this->ack($key);
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            throw $e;
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

    protected function getCacheDir()
    {
        if ($this->cacheDir === null) {
            // TODO: tmpdir?
            $this->cacheDir = Icinga::app()
                ->getModuleManager()
                ->getModule('iet')
                ->getBaseDir();
        }

        return $this->cacheDir;
    }

    protected function getCached($key)
    {
        $file =  $this->getCacheDir() . "/iet-$key.json";
        if (file_exists($file)) {
            return (array) \json_decode(\file_get_contents($file));
        } else {
            return null;
        }
    }
}
