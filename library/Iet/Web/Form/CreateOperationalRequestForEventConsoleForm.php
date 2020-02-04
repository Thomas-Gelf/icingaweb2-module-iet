<?php

namespace Icinga\Module\Iet\Web\Form;

use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;

class CreateOperationalRequestForEventConsoleForm extends BaseOperationalRequestForm
{
    /** @var Issue */
    protected $issue;

    public function __construct(Issue $issue)
    {
        $this->issue = $issue;
        parent::__construct();
    }

    protected function addMessageDetails()
    {
        $issue = $this->issue;

        $host = $issue->get('host_name');
        $object = $issue->get('object_name');
        $severity = $issue->get('severity');
        $title = "EVENT: ";
        if ($host === null) {
            $title .= $object;
        } elseif ($object === null) {
            $title .= $host;
        } else {
            $title .= "$host : $object";
        }

        $this->addElement('text', 'title1', [
            'label'       => $this->translate('Title'),
            'required'    => true,
            'value'       => $title,
            'description' => $this->translate(
                'Summary of this incident'
            ),
        ]);

        $this->addElement('textarea', 'details', array(
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => $this->issue->get('message'),
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ));
    }

    protected function fillLinkPattern($link)
    {
        return ConfigHelper::fillPlaceholders($link, $this->issue);
    }

    protected function ack($ietKey)
    {
        $this->issue->setTicketRef($ietKey);
        $this->issue->setOwner($this->getValue('rep'));
        $this->issue->storeToDb(DbFactory::db());
    }
}
