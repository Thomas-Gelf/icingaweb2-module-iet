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
        $helper = $this->helper;

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
            'value'       => $helper->getDefault('icingahost'),
        ]);
        $this->addElement('text', 'Monitor', [
            'label'       => $this->translate('Object / Service'),
            'required'    => true,
            'value'       => $helper->getDefault('icingaservice', 'host problem'),
        ]);
        $this->addElement('text', 'Ticketgroup', [
            'label'       => $this->translate('Ticketgroup'),
            'required'    => false,
            'value'       => $this->getDefaultFromConfig('Ticketgroup'),
        ]);
        $this->addElement('text', 'Priority', [
            'label'       => $this->translate('Priority'),
            'required'    => false,
            'value'       => $helper->getDefault('state'),
        ]);
        $this->addElement('text', 'ShortDesc', [
            'label'       => $this->translate('Summary'),
            'required'    => true,
            'value'       => \strip_tags($helper->getDefault('title')),
            'description' => $this->translate('Short problem summary'),
        ]);
        $this->addElement('textarea', 'Desc', [
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => \strip_tags($helper->getDefault('details')),
            'rows'        => 8,
            'description' => $this->translate('Message body of this issue'),
        ]);
    }
}
