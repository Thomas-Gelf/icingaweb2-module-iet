<?php

namespace Icinga\Module\Iet\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    /**
     * @param Host $host
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getActionsForHost(Host $host)
    {
        $auth = Auth::getInstance();
        $urls = [];

        if (Config::module('iet')->get('implementation', 'ticket_form')) {
            if ($auth->hasPermission('iet/ticket/create')) {
                $urls[mt('iet', 'Create Ticket')] = Url::fromPath('iet/ticket/create', [
                    'host' => $host->host_name,
                ]);
            }
        } elseif ($auth->hasPermission('iet/or/create')) {
            $urls[mt('iet', 'Create Operational Request')] = Url::fromPath('iet/or/create', [
                'host' => $host->host_name,
            ]);
        }

        return $urls;
    }
}
