<?php

namespace Icinga\Module\Iet\Web\Form;

use Icinga\Application\Config as WebConfig;
use Icinga\Module\Eventtracker\ConfigHelper;
use Icinga\Module\Eventtracker\DbFactory;
use Icinga\Module\Eventtracker\File;
use Icinga\Module\Eventtracker\Issue;
use Icinga\Module\Eventtracker\SetOfIssues;
use ipl\Html\Html;
use ipl\Stdlib\Str;

class CreateOperationalRequestForEventConsoleForm extends BaseOperationalRequestForm
{
    /** @var SetOfIssues */
    protected $issues;

    private $defaultMessage;

    private $defaultTitle;

    public function __construct(SetOfIssues $issues)
    {
        $this->issues = $issues;
        parent::__construct();
        $this->prepareOrValues();
    }

    protected function prepareOrValues()
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
                    \preg_replace('/^(.+)[\r\n].+?$/s', '\1', \trim(\strip_tags($issue->get('message'))))
                );
            }

            \natcasesort($messages);
            $i = 0;
            foreach ($messages as & $message) {
                $i++;
                $message = "$i) $message";
            }
            unset($message);

            $message = \implode("\n", $messages);

            $maxLength = 4096; // TODO: verify
            if (strlen($message) > $maxLength) {
                $singleMax = (int) floor($maxLength / count($messages));
                array_map(function ($string) use ($singleMax) {
                    return substr($string, 0, $singleMax);
                }, $messages);
                $message = \implode("\n", $messages);
            }

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

        $this->defaultTitle = $title;
        $this->defaultMessage = $message;
    }

    protected function addMessageDetails()
    {
        $this->addElement('text', 'title1', [
            'label'       => $this->translate('Title'),
            'required'    => true,
            'value'       => $this->defaultTitle,
            'description' => $this->translate(
                'Summary of this incident'
            ),
        ]);

        $this->addElement('textarea', 'details', [
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => $this->defaultMessage,
            'rows'        => 6,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ]);
    }

    protected function shorten($string, $length)
    {
        if (\strlen($string) <= $length) {
            return $string;
        }

        return \substr($string, 0, $length) . '...';
    }

    protected function addLinks($id)
    {
        $issues = $this->issues->getIssues();
        $isMulti = \count($issues) > 1;
        $i = 0;
        foreach ($issues as $issue) {
            $i++;
            foreach ($this->getLinksForIssue($issue) as $name => $link) {
                $this->api->addLinkToOR($id, $isMulti ? "$name $i" : $name, $link);
            }
        }
    }

    protected function getLinksForPreview(): array
    {
        $links = [];

        $issues = $this->issues->getIssues();
        $isMulti = \count($issues) > 1;
        $i = 0;
        foreach ($issues as $issue) {
            $i++;
            foreach ($this->getLinksForIssue($issue) as $name => $link) {
                $links[] = Html::tag('li', Html::tag('a', [
                    'href' => $link,
                    'target' => '_blank'
                ], $isMulti ? "$name $i" : $name));
            }
        }

        return $links;
    }

    protected function getLinksForIssue(Issue $issue): array
    {
        $configuredLinks = WebConfig::module('iet')->getSection('links');
        $links = [];
        foreach ($configuredLinks as $name => $value) {
            $link = $this->fillPlaceholdersForIssue($value, $issue);
            if (\strlen($link) > 0) {
                $links[$name] = $link;
            }
        }

        return $links;
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

    protected function addFiles($id): void
    {
        $uuids = [];
        $checksums = [];

        if (! $this->hasElement('files')) {
            return;
        }
        foreach ($this->getElement('files')->getValue() as $value) {
            list($uuid, $checksum) = Str::symmetricSplit($value, '!', 2);
            $uuids[] = hex2bin($uuid);
            $checksums[] = hex2bin($checksum);
        }

        foreach (File::loadByIssueUuidsAndChecksums($uuids, $checksums, DbFactory::db()) as $file) {
            $this->api->attachFileToOR($id, $file->get('filename'), $file->get('data'));
        }
    }

    protected function provideFiles(): array
    {
        $files = [];

        foreach (File::loadAllBySetOfIssues($this->issues, DbFactory::db()) as $file) {
            $key = bin2hex($file->get('checksum')) . $file->get('filename');

            if (isset($files[$key])) {
                continue;
            }

            $files[$key] = $file;
        }

        return array_values($files);
    }
}
