<?php

namespace Icinga\Module\Iet\Controllers;

use Icinga\Module\Iet\Web\Controller;
use Icinga\Module\Iet\Web\Form\CreateOperationalRequestForm;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Monitoring\Backend;
use ipl\Html\Html;

class OrController extends Controller
{
    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function createAction()
    {
        $this->assertPermission('iet/or/create');
        $this->runFailSafe('showCreateForm');
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     */
    protected function showCreateForm()
    {
        $host = $this->params->getRequired('host');
        $service = $this->params->get('service');
        $host = 'app1.example.com';
        $service = null;

        $this->addTitle(
            $this->translate('Create Operational Request') . $this->titleSuffix($host, $service)
        );

        $this->addSingleTab($this->translate('Create OR'));

        $form = new CreateOperationalRequestForm();
        $params = ['host' => $host];
        if ($service) {
            $params['service'] = $service;
            $object = new Service(Backend::instance(), $host, $service);
            $form->setSuccessUrl('monitoring/service/show', [
                'host'    => $host,
                'service' => $service,
            ]);
        } else {
            $object = new Host(Backend::instance(), $host);
            $form->setSuccessUrl('monitoring/host/show', [
                'host' => $host
            ]);
        }

        if (! $object->fetch()) {
            $this->content()->add(Html::tag('p', [
                'class' => 'error',
            ], $this->translate('Monitored object has not been found')));

            return;
        }
        $wrapper = Html::tag('div', ['class' => 'icinga-module module-director']);

        $form->setObject($object)
            ->handleRequest($this->getServerRequest());
        $wrapper->add($form);

        $this->content()->add($wrapper);
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
