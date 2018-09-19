<?php

namespace Icinga\Module\Iet\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Iet\Config;

class ApiCommand extends Command
{
    public function sourceSystemsAction()
    {
        $this->renderEnum($this->api()->listSourceSystems());
    }

    public function datetimeAction()
    {
        echo $this->api()->getDateTime() . "\n";
    }

    public function reportersAction()
    {
        $this->renderEnum(
            $this->api()->listReporters()
        );
    }

    public function groupsAction()
    {
        $this->renderGroupsTree(
            $this->api()->buildGroupsTree(! $this->params->get('all'))
        );
    }

    public function listGroupsAction()
    {
        $this->renderEnum($this->api()->listGroups());
    }

    public function categoriesAction()
    {
        $this->renderEnum($this->api()->listCategories());
    }

    public function createORAction()
    {
        $p = $this->params;
        $params = [
            'service'        => $p->get('service', 'iET Enterprise'),
            'requesteddate'  => $p->get('requesteddate', date('d.m.Y')),
        ];
        $required = [
            'title'          => 'title1',  // Just a test OR
            'details'        => 'details', // Blabla, please ignore / delete this
            'group'          => 'repgrp', // ITZF-IAM -> owner group?
            'reporter'       => 'rep', // GLM -> RepId? -> getReps
            'caller'         => 'caller', // User? glm
            'fe'             => 'fe',  // ? BBFH-IET -> Gruppe: ITSM Application
            'sourcesystemid' => 'sourcesystemid',
        ];
        $optional = [];
        foreach ($required as $param => $name) {
            $params[$name] = $p->getRequired($param);
        }
        foreach ($optional as $param => $name) {
            $value = $value = $p->get($param);
            if (strlen($value)) {
                $params[$name] = $value;
            }
        }

        printf(
            "Ticket created: %s\n",
            $this->screen->colorize($this->api()->createOR($params), 'green')
        );
    }

    public function cisAction()
    {
        print_r($this->api()->fetchActiveCIsByCategory(
            $this->params->getRequired('category')
        ));
    }

    protected function api()
    {
        return Config::getApi($this->params->get('instance'));
    }

    protected function renderEnum($enum)
    {
        $s = $this->screen;
        $maxlen = 1;
        $allNumeric = true;
        foreach (array_keys($enum) as $name) {
            $maxlen = max($maxlen, strlen($name));
            if ($allNumeric && ! (is_int($name) || ctype_digit($name))) {
                $allNumeric = false;
            }
        }

        $nameFormat = $allNumeric ? "%${maxlen}d" : "%${maxlen}s";
        foreach ($enum as $name => $value) {
            printf(
                "%s: %s\n",
                $s->colorize(sprintf($nameFormat, $name), 'darkgray'),
                $s->colorize($value, 'green')
            );
        }
    }

    protected function renderGroupsTree($tree, $level = 0)
    {
        $screen = $this->screen;
        foreach ($tree as $name => $group) {
            printf(
                "%s%s: %s\n",
                $this->makeIndent($level),
                $group->canBeAssigned ? $screen->colorize($name, 'green') : $name,
                $group->description
            );
            $this->renderGroupsTree($group->children, $level + 1);
        }
    }

    protected function makeIndent($level)
    {
        return (
            $level > 1
            ? str_repeat('  ', $level - 1)
            : ''
        ) . (
            $level > 0 ? '* ' : ''
        );
    }
}

