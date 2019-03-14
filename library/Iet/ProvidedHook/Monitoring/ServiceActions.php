<?php

namespace Icinga\Module\Iet\ProvidedHook\Monitoring;

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

        if ($auth->hasPermission('iet/or/create')) {
            $urls[mt('iet', 'Create Operational Request')] = Url::fromPath('iet/or/create', [
                'host'    => $service->host_name,
                'service' => $service->service_description,
            ]);
        }

        return $urls;
    }
}
