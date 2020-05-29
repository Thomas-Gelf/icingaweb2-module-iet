<?php

namespace Icinga\Module\Iet\Web\Form;

use Icinga\Authentication\Auth;

class MinimalMonitoringTicketForm extends BaseMonitoringTicketForm
{
    protected $ietProcessName = 'CreateNewIncident';

    protected function addMessageDetails()
    {
        $myUsername = Auth::getInstance()->getUser()->getUserName();

        $this->addElement('text', 'UserID', [
            'label'       => $this->translate('UserID'),
            'required'    => false,
            'value'       => $myUsername,
            // 'value'       => $this->getObjectDefault('UserID'),
        ]);
        $this->addElement('text', 'Hostname', [
            'label'       => $this->translate('Hostname'),
            'required'    => false,
            'value'       => $this->getObjectDefault('eMail'),
        ]);
        $this->addElement('text', 'Group', [
            'label'       => $this->translate('Group'),
            'required'    => false,
            'value'       => $this->getObjectDefault('Group'),
        ]);
        $this->addElement('textarea', 'IncidentText', [
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => \strip_tags(
                $this->getObjectDefault('title') . "\n" .
                $this->getObjectDefault('details')
            ),
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ]);
    }
}
