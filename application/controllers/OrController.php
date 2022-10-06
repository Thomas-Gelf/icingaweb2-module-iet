<?php

namespace Icinga\Module\Iet\Controllers;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\IssueHistory;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Eventtracker\Uuid;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\Web\Controller;
use Icinga\Module\Iet\Web\Form\CreateOperationalRequestForEventConsoleForm;
use Icinga\Module\Iet\Web\Form\CreateOperationalRequestForm;
use Icinga\Module\Iet\Web\Widget\OperationalRequestDetails;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
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
        $this->content()->add(new OperationalRequestDetails($or));
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function createAction()
    {
        $this->assertPermission('iet/or/create');
        $this->runFailSafe(function () {
            $this->showCreateForm();
        });
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function issueAction()
    {
        $this->assertPermission('iet/or/create');
        $this->runFailSafe(function () {
            $this->showIssueForm();
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
            $this->translate('Create Operational Request') . $this->titleSuffix($host, $service)
        );

        $this->addSingleTab($this->translate('Create OR'));

        $form = new CreateOperationalRequestForm();
        $params = ['host' => $host];
        if ($service) {
            $params['service'] = $service;
            $object = new Service(MonitoringBackend::instance(), $host, $service);
            $url = Url::fromPath('monitoring/service/show', $params);
        } else {
            $object = new Host(MonitoringBackend::instance(), $host);
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

        $this->content()->add($form);
    }

    protected function showIssueForm()
    {
        $db = DbFactory::db();
        $uuid = $this->params->get('uuid');
        if ($uuid === null) {
            $issues = SetOfIssues::fromUrl($this->url(), $db);
            $count = \count($issues);
            $this->addTitle($this->translate('%d issues'), $count);
        } else {
            $uuid = Uuid::toBinary($uuid);
            if ($issue = Issue::loadIfExists($uuid, $db)) {
                $issues = new SetOfIssues($db, [$issue]);
            } elseif (IssueHistory::exists($uuid, $db)) {
                $this->addTitle($this->translate('Issue has been closed'));
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint ok'
                ], $this->translate('This issue has already been closed.')
                    . ' '
                    . $this->translate('Future versions will show an Issue history in this place')));

                return;
            } else {
                $this->addTitle($this->translate('Not found'));
                $this->content()->add(Html::tag('p', [
                    'class' => 'state-hint error'
                ], $this->translate('There is no such issue')));

                return;
            }
        }

        $this->addTitle(
            $this->translate('Create Operational Request')
        );

        $this->addSingleTab($this->translate('Create OR'));
        $form = new CreateOperationalRequestForEventConsoleForm($issues);
        $form->on(Form::ON_SUCCESS, function (CreateOperationalRequestForEventConsoleForm $form) use ($issues) {
            /** @var Issue $issue */
            $issue = \current($issues->getIssues());
            $this->redirectNow(Url::fromPath('eventtracker/issue', [
                'uuid' => $issue->getNiceUuid()
            ]));
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
