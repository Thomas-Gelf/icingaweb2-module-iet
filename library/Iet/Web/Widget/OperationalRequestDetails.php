<?php

namespace Icinga\Module\Iet\Web\Widget;

use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\OperationalRequest;
use Icinga\Module\Iet\WorklogEntry;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class OperationalRequestDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'iet-or'
    ];

    protected $or;

    public function __construct(OperationalRequest $or)
    {
        $this->or = $or;
    }

    protected function assemble()
    {
        $or = $this->or;
        $host = Config::getSetting(null, 'host');

        $url = \sprintf('iet://%s/displayrecord?or=%d', $host, $or->id);

        $this->add((new NameValueTable())->addNameValuePairs([
            $this->translate('Title') => $or->title,
            $this->translate('ID')    => Html::tag('a', $or->id, [
                'href'   => $url,
                'target' => '_blank',
                'title'  => $this->translate('Open Operational Request in iET'),
            ]),
            $this->translate('Caller') => $or->caller,
            $this->translate('Reporter (Group)') => \sprintf(
                '%s (%s)',
                $or->rep,
                $or->repgrp
            ),
            $this->translate('FE (Rep)') => \sprintf(
                '%s (%s)',
                $or->fe,
                $or->ferep
            ),
        ]));

        $this->add(Html::tag('pre', $this->or->details));

        if (empty($or->worklog)) {
            return;
        }

        $this->add(Html::tag('h3', $this->translate('Worklog')));
        /** @var WorklogEntry $entry */
        foreach ($or as $entry) {
            $this->add(Html::tag('div', [
                'class' => 'iet-worklog-entry'
            ], [
                Html::tag('h4', $entry->topic),
                Html::tag('p', $entry->entry)
            ]));
        }
    }
}
