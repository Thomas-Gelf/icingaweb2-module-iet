<?php

namespace Icinga\Module\Iet\ProvidedHook\Eventtracker;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Incident;

class EventActions extends EventActionsHook
{
    use TranslationHelper;

    /**
     * @param Incident $incident
     * @return array
     */
    public function getIncidentActions(Incident $incident)
    {
        $auth = Auth::getInstance();
        $links = [];

        if ($auth->hasPermission('iet/or/create')) {
            $links[] = Link::create(
                $this->translate('Create OR'),
                'iet/or/create',
                [
                    'incident' => $incident->getHexUuid(),
                    'message'  => substr($incident->get('message'), 0, 512),
                    'host'     => $incident->get('host_name'),
                    'object'   => $incident->get('object_name'),
                    'severity' => $incident->get('severity'),
                ],
                [
                    'class' => 'icon-forward',
                    'title' => $this->translate('Create Operational Request'),
                ]
            );
        }

        return $links;
    }
}
