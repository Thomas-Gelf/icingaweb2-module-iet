<?php

namespace Icinga\Module\Iet\Web\Form;

use Icinga\Authentication\Auth;

class MinimalMonitoringTicketForm extends BaseMonitoringTicketForm
{
    /** @var string I would prefer to see 'CreateNewIncident' or similar */
    protected $ietProcessName = 'icingaCreateUserAction';

    protected function addMessageDetails()
    {
        $myUsername = Auth::getInstance()->getUser()->getUserName();

        $this->addElement('hidden', 'Action', [
            'value' => 'createIncident'
        ]);
        $this->addElement('text', 'UserID', [
            'label'       => $this->translate('UserID'),
            'required'    => false,
            'value'       => $myUsername,
            // 'value'       => $this->getObjectDefault('UserID'),
        ]);
        $this->addElement('text', 'Hostname', [
            'label'       => $this->translate('Hostname'),
            'required'    => false,
            'value'       => $this->getObjectDefault('icingahost'),
        ]);
        $this->addElement('text', 'Monitor', [
            'label'       => $this->translate('Object / Service'),
            'required'    => true,
            'value'       => $this->getObjectDefault('icingaservice') ?: 'host problem',
        ]);
        $this->addElement('text', 'Ticketgroup', [
            'label'       => $this->translate('Ticketgroup'),
            'required'    => false,
            'value'       => $this->getObjectDefault('Ticketgroup'),
        ]);
        $this->addElement('text', 'Priority', [
            'label'       => $this->translate('Priority'),
            'required'    => false,
            'value'       => $this->getObjectDefault('state'),
        ]);
        $this->addElement('text', 'ShortDesc', [
            'label'       => $this->translate('Summary'),
            'required'    => true,
            'value'       => \strip_tags(
                $this->getObjectDefault('title')
            ),
            'description' => $this->translate('Short problem summary'),
        ]);
        $this->addElement('textarea', 'Desc', [
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => \strip_tags(
                $this->getObjectDefault('details')
            ),
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ]);
    }
}
