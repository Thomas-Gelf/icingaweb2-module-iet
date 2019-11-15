<?php

namespace Icinga\Module\Iet\ProvidedHook\Eventtracker;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;

class EventActions extends EventActionsHook
{
    use TranslationHelper;

    /**
     * @param Issue $issue
     * @return array
     */
    public function getIssueActions(Issue $issue)
    {
        $auth = Auth::getInstance();
        $links = [];

        if ($auth->hasPermission('iet/or/create')) {
            // TODO: link to dedicated controller. iet/or/issue?issue=<hex>
            // -> get data from eventtracker
            // -> set or id
            $links[] = Link::create(
                $this->translate('Create OR'),
                'iet/or/issue',
                [
                    'issue' => $issue->getHexUuid(),
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
