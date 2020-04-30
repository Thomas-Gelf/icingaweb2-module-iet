<?php

namespace Icinga\Module\Iet\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Monitoring\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    /**
     * @param Service $service
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getActionsForService(Service $service)
    {
        $auth = Auth::getInstance();
        $urls = [];

        if (Config::module('iet')->get('defaults', 'ticket_form')) {
            if ($auth->hasPermission('iet/ticket/create')) {
                $urls[mt('iet', 'Create Ticket')] = Url::fromPath('iet/ticket/create', [
                    'host'    => $service->host_name,
                    'service' => $service->service_description,
                ]);
            }
        } elseif ($auth->hasPermission('iet/or/create')) {
            $urls[mt('iet', 'Create Operational Request')] = Url::fromPath('iet/or/create', [
                'host'    => $service->host_name,
                'service' => $service->service_description,
            ]);
        }

        return $urls;
    }
}
