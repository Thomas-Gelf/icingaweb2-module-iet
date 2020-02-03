<?php

namespace Icinga\Module\Iet\Controllers;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\Web\Controller;
use Icinga\Module\Iet\Web\Form\CreateOperationalRequestForEventConsoleForm;
use Icinga\Module\Iet\Web\Form\CreateOperationalRequestForm;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Monitoring\Backend;
use ipl\Html\Form;
use ipl\Html\Html;

class OrController extends Controller
{
    public function indexAction()
    {
        $api = Config::getApi();
        $id = $this->params->getRequired('id');
        $or = $api->getOR($id);
        $this->addSingleTab($this->translate('OR Details'));
        $this->addTitle(\sprintf(
            $this->translate('Operational Request #%s'),
            $id
        ));
        $this->content()->add();
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function createAction()
    {
        $this->assertPermission('iet/or/create');
        $this->runFailSafe('showCreateForm');
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function issueAction()
    {
        $this->assertPermission('iet/or/create');
        $this->runFailSafe('showIssueForm');
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
            $this->translate('Create Operational Request') . $this->titleSuffix($host, $service)
        );

        $this->addSingleTab($this->translate('Create OR'));

        $form = new CreateOperationalRequestForm();
        $params = ['host' => $host];
        if ($service) {
            $params['service'] = $service;
            $object = new Service(Backend::instance(), $host, $service);
            $url = Url::fromPath('monitoring/service/show', $params);
        } else {
            $object = new Host(Backend::instance(), $host);
            $url = Url::fromPath('monitoring/host/show', $params);
        }

        if (! $object->fetch()) {
            $this->content()->add(Html::tag('p', [
                'class' => 'error',
            ], $this->translate('Monitored object has not been found')));
            return;
        }

        $form->on(Form::ON_SUCCESS, function (CreateOperationalRequestForm $form) use ($url) {
            $this->redirectNow($url);
        })->setObject($object)->handleRequest($this->getServerRequest());

        $this->addForm($form);
    }

    protected function showIssueForm()
    {
        $uuid = $this->params->getRequired('uuid');
        $issue = Issue::load(hex2bin($uuid), DbFactory::db());

        $this->addTitle(
            $this->translate('Create Operational Request')
        );

        $this->addSingleTab($this->translate('Create OR'));
        $form = new CreateOperationalRequestForEventConsoleForm($issue);
        $form->handleRequest($this->getServerRequest());
        $form->on(Form::ON_SUCCESS, function (CreateOperationalRequestForEventConsoleForm $form) use ($uuid) {
            $this->redirectNow(Url::fromPath('eventtracker/issue', [
                'uuid' => $uuid
            ]));
        });

        $this->addForm($form);
    }

    protected function addForm(Form $form)
    {
        $wrapper = Html::tag('div', ['class' => 'icinga-module module-director']);
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
