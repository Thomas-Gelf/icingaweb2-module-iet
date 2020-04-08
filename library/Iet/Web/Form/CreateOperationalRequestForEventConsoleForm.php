<?php

namespace Icinga\Module\Iet\Web\Form;

use Icinga\Application\Config as WebConfig;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\SetOfIssues;

class CreateOperationalRequestForEventConsoleForm extends BaseOperationalRequestForm
{
    /** @var SetOfIssues */
    protected $issues;

    public function __construct(SetOfIssues $issues)
    {
        $this->issues = $issues;
        parent::__construct();
    }

    protected function addMessageDetails()
    {
        $issues = $this->issues->getIssues();
        if (\count($issues) === 1) {
            $issue = \current($issues);
            $host = $issue->get('host_name');
            $object = $issue->get('object_name');
            $message = \strip_tags($issue->get('message'));
            // UNUSED: $severity = $issue->get('severity');
        } else {
            $hosts = [];
            $messages = [];
            foreach ($issues as $issue) {
                $hosts[$issue->get('host_name')] = true;
            }
            foreach ($issues as $issue) {
                if (\count($hosts) === 1) {
                    $object = $issue->get('object_name');
                } else {
                    $object = \sprintf('%s : %s', $issue->get('host_name'), $issue->get('object_name'));
                }
                $messages[] = \sprintf(
                    '%s: %s',
                    $object,
                    $this->shorten(
                        \preg_replace('/^(.+)[\r\n].+?$/s', '\1', \trim(\strip_tags($issue->get('message')))),
                        120
                    )
                );
            }

            \natcasesort($messages);
            $i = 0;
            foreach ($messages as & $message) {
                $i++;
                $message = "$i) $message";
            }

            $message = \implode("\n", $messages);
            $hosts = \array_keys($hosts);
            $object = \sprintf('%s problems', count($issues));
            if (\count($hosts) === 1) {
                $host = $hosts[0];
            } else {
                $host = \sprintf('%s and %d more host(s)', $hosts[0], \count($hosts) - 1);
            }
        }

        $title = \sprintf(
            'EVENT: %s ',
            \strtoupper($this->issues->getWorstSeverity())
        );
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
            'value'       => $message,
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ));
    }

    protected function shorten($string, $length)
    {
        if (\strlen($string) <= $length) {
            return $string;
        } else {
            return \substr($string, 0, $length) . '...';
        }
    }

    protected function addLinks($id)
    {
        $configuredLinks = WebConfig::module('iet')->getSection('links');
        $issues = $this->issues->getIssues();
        if (\count($issues) === 1) {
            $issue = $issues[0];
            foreach ($configuredLinks as $name => $value) {
                $link = $this->fillPlaceholdersForIssue($value, $issue);
                if (\strlen($link) > 0) {
                    $this->api->addLinkToOR($id, $name, $link);
                }
            }
        } else {
            foreach ($configuredLinks as $name => $value) {
                $i = 0;
                foreach ($issues as $issue) {
                    $i++;
                    $link = $this->fillPlaceholdersForIssue($value, $issue);
                    if (\strlen($link) > 0) {
                        $this->api->addLinkToOR($id, "$name $i", $link);
                    }
                }
            }
        }
    }

    protected function fillPlaceholdersForIssue($string, Issue $issue)
    {
        return ConfigHelper::fillPlaceHoldersForIssue($string, $issue, DbFactory::db());
    }

    protected function fillPlaceholders($string)
    {
        return $this->fillPlaceholdersForIssue($string, \current($this->issues->getIssues()));
    }

    protected function ack($ietKey)
    {
        foreach ($this->issues->getIssues() as $issue) {
            $issue->setTicketRef($ietKey);
            $issue->setOwner($this->getValue('rep'));
            $issue->storeToDb(DbFactory::db());
        }
    }
}
