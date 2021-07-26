<?php

namespace Icinga\Module\Iet\Controllers;

use gipfl\IcingaWeb2\Url;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Iet\Web\Controller;
use Icinga\Module\Iet\Web\Form\BaseMonitoringTicketForm;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Monitoring\Backend;
use ipl\Html\Form;
use ipl\Html\Html;

class TicketController extends Controller
{
    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function createAction()
    {
        $this->assertPermission('iet/ticket/create');
        $this->runFailSafe(function () {
            $this->showCreateForm();
        });
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     */
    protected function showCreateForm()
    {
        $host = $this->params->getRequired('host');
        $service = $this->params->get('service');

        $this->addTitle(
            $this->translate('Create a new Ticket') . $this->titleSuffix($host, $service)
        );

        $this->addSingleTab($this->translate('Create Ticket'));

        // TODO: Not sure whether 'default' is the right place. Should we fill placeholders?
        $implementation = $this->Config()->get('implementation', 'ticket_form');
        if ($implementation === null) {
            throw new NotFoundError('No ticket_form has been defined');
        }
        $class = "\\Icinga\\Module\\Iet\\Web\\Form\\${implementation}Form";
        $params = ['host' => $host];
        if ($service) {
            $params['service'] = $service;
            $object = new Service(Backend::instance(), $host, $service);
            $url = Url::fromPath('monitoring/service/show', $params);
        } else {
            $object = new Host(Backend::instance(), $host);
            $url = Url::fromPath('monitoring/host/show', $params);
        }
        /** @var BaseMonitoringTicketForm $form */
        $form = new $class($object);

        if (! $object->fetch()) {
            $this->content()->add(Html::tag('p', [
                'class' => 'error',
            ], $this->translate('Monitored object has not been found')));
            return;
        }

        $form->on(Form::ON_SUCCESS, function (BaseMonitoringTicketForm $form) use ($url) {
            $this->redirectNow($url);
        })->handleRequest($this->getServerRequest());

        $this->content()->add($form);
    }

    protected function titleSuffix($host, $service)
    {
        if ($host === null) {
            return '';
        } else {
            if ($service) {
                return ": $service on $host";
            } else {
                return ": $host";
            }
        }
    }
}
