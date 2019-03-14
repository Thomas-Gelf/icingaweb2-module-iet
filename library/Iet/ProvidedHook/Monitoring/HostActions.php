<?php

namespace Icinga\Module\Iet\ProvidedHook\Monitoring;

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

        if ($auth->hasPermission('iet/or/create')) {
            $urls[mt('iet', 'Create Operational Request')] = Url::fromPath('iet/or/create', [
                'host' => $host->host_name,
            ]);
        }

        return $urls;
    }
}
