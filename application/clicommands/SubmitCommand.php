<?php

namespace Icinga\Module\Iet\Clicommands;

use gipfl\Translation\StaticTranslator;
use gipfl\Web\Form;
use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\SetOfIssues;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\Web\Form\CreateOperationalRequestForEventConsoleForm;
use Ramsey\Uuid\Uuid;
use RingCentral\Psr7\ServerRequest;

class SubmitCommand extends Command
{
    /**
     * Create an issue for the given Host or Service problem
     *
     * Use this as a NotificationCommand for Icinga
     *
     * USAGE
     *
     * icingacli iet submit operationalRequest [options]
     *
     * REQUIRED OPTIONS
     *
     *   --issue <issue-uuid>  Eventtracker module issue UUID
     *
     * OPTIONAL
     *
     *   --ietInstance         Refers to a configured instance
     *                         (defaults to the first one)
     *   --anyKey <value>      fills/overrides the anyKey form field
     *
     * FLAGS
     *   --verbose    More log information
     *   --trace      Get a full stack trace in case an error occurs
     *   --benchmark  Show timing and memory usage details
     */
    public function operationalRequestAction()
    {
        $db = DbFactory::db();
        $p = $this->params;
        $issueUuid = Uuid::fromString($this->params->shiftRequired('issue'));
        $instance = $this->params->shift('ietInstance');
        if ($instance === null) {
            $instance = Config::getDefaultName();
        }
        $params = [
            'submit'       => 'Create',
            'iet_instance' => $instance,
        ] + $p->getParams();
        $issue = Issue::load($issueUuid->getBytes(), $db);
        $issues = new SetOfIssues($db, [$issue]);
        $form = new CreateOperationalRequestForEventConsoleForm($issues);
        Logger::getInstance()->setLevel(Logger::DEBUG); // Hint: notifications go to logger only

        if (! $this->submitForm($form, $params)) {
            $this->fail('Submitting operationalRequest failed for unknown reason');
        }
    }

    protected function submitForm(Form $form, $params): bool
    {
        StaticTranslator::setNoTranslator();
        $form->disableCsrf()->doNotCheckFormName();

        return $this->validateRequestWithForm(
            (new ServerRequest('POST', 'cli'))->withParsedBody($params),
            $form
        );
    }

    protected function validateRequestWithForm(ServerRequest $request, Form $form): bool
    {
        $success = false;
        $form->on($form::ON_SUCCESS, function () use (&$success) {
            $success = true;
        });
        $form->handleRequest($request);
        if (! $form->isValid()) {
            foreach ($form->getElements() as $element) {
                if (! $element->isValid()) {
                    foreach ($element->getMessages() as $message) {
                        $this->fail(sprintf('--%s: %s', $element->getName(), $this->wantErrorMessage($message)));
                    }
                    $this->fail(sprintf('--%s is not valid', $element->getName()));
                }
                if ($element->isRequired() && $element->getValue() === null) {
                    $this->fail(sprintf('--%s <value> is required', $element->getName()));
                }
            }
            $this->fail('Validation failed for unknown reasons');
        }
        foreach ($form->getMessages() as $message) {
            $this->fail($this->wantErrorMessage($message));
        }

        return $success;
    }

    protected function wantErrorMessage($message)
    {
        if ($message instanceof \Exception) {
            return $message->getMessage();
        }

        return $message;
    }
}
