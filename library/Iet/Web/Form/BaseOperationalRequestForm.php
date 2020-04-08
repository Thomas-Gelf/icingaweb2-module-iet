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
    protected $api;

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

        $defaultSourceSystem = $this->getDefaultFromConfig('sourcesystem', $sourceSystems);
        $defaultFe = $this->getDefaultFromConfig('fe', $allGroups);

        $defaultReportingGroup = $this->api->getReportersDefaultGroup($myUsername);
        $this->addElement('select', 'sourcesystemid', [
            'label'        => $this->translate('Source System'),
            'multiOptions' => $this->optionalEnum($sourceSystems),
            'value'        => $defaultSourceSystem,
            'required'     => true,
        ]);
        $this->addElement('select', 'repgrp', [
            'label'        => $this->translate('Group (Reporter)'),
            'multiOptions' => $groups,
            'required'     => true,
            'value'        => $defaultReportingGroup,
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
            'value'        => $defaultFe,
            'required'     => true,
        ]);
        $this->addElement('text', 'service', [
            'label'    => $this->translate('Service'),
            'value'    => $this->getDefaultFromConfig('service'),
        ]);

        $this->addMessageDetails();

        $this->addElement('text', 'topic', [
            'label'    => $this->translate('Worklog Topic'),
            'ignore'   => true,
            'required' => false,
        ]);
        $this->addElement('text', 'requesteddate', [
            'label'    => $this->translate('Requested Date'),
            'value'    => \date('d.m.Y', \time() + 86400 * 4),
        ]);

        $this->addElement('textarea', 'entry', [
            'label'    => $this->translate('Worklog Entry'),
            'rows'        => 8,
            'ignore'   => true,
            'required' => false,
        ]);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Create')
        ]);
    }

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
            $topic = $this->getValue('topic');
            $entry = $this->getValue('entry');
            if ($topic || $entry) {
                $this->api->updateOR($key, [
                    'topic' => $topic,
                    'entry' => $entry,
                ]);
            }
            $this->addLinks($key);
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            throw $e;
        }

        return $key;
    }

    protected function addLinks($id)
    {
        foreach (WebConfig::module('iet')->getSection('links') as $name => $value) {
            $link = $this->fillPlaceholders($value);
            if (\strlen($link) > 0) {
                $this->api->addLinkToOR($id, $name, $this->fillPlaceholders($value));
            }
        }
    }

    protected function fillPlaceholders($string)
    {
        return $string;
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
