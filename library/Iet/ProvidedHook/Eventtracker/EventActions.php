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
            $links[] = Link::create($this->translate('Create OR'), 'iet/or/issue', [
                'uuid' => $issue->getHexUuid(),
            ], [
                'class' => 'icon-forward',
                'title' => $this->translate('Create Operational Request'),
            ]);
        }

        return $links;
    }
}
