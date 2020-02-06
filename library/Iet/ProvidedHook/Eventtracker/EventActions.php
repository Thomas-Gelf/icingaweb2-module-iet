<?php

namespace Icinga\Module\Iet\ProvidedHook\Eventtracker;

use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Eventtracker\Hook\EventActionsHook;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\SetOfIssues;
use ipl\Html\Html;

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

        $ref = $issue->get('ticket_ref');
        if ($ref !== null) {
            $links[] = Link::create($ref, 'iet/or', [
                'id' => $ref,
            ], [
                'title' => $this->translate('Show Operational Request'),
            ]);
        } elseif ($auth->hasPermission('iet/or/create')) {
            $links[] = Link::create($this->translate('Create OR'), 'iet/or/issue', [
                'uuid' => $issue->getHexUuid(),
            ], [
                'class' => 'icon-forward',
                'title' => $this->translate('Create Operational Request'),
            ]);
        }

        return $links;
    }

    /**
     * @param SetOfIssues $issues
     * @return array
     */
    public function getIssuesActions(SetOfIssues $issues)
    {
        $auth = Auth::getInstance();
        $links = [];

        if ($auth->hasPermission('iet/or/create')) {
            $filter = Filter::matchAny();
            foreach ($issues->getIssues() as $issue) {
                $filter->addFilter(Filter::matchAll(Filter::where('uuid', $issue->getHexUuid())));
            }
            $link = Url::fromPath('iet/or/issue');
            $url = $link->getAbsoluteUrl() . '?' . $filter->toQueryString();
            $links[] = Html::tag('a', [
                'href'  => $url,
                'class' => 'icon-forward',
                'title' => $this->translate('Create Operational Request'),
            ], $this->translate('Create OR'));
        }

        return $links;
    }
}
