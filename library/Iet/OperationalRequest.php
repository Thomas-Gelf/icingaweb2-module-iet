<?php

namespace Icinga\Module\Iet;

use SimpleXMLElement;

class OperationalRequest
{
    public $id;

    public $title;

    public $rep;

    public $repgrp;

    public $fe;

    public $ferep;

    public $caller;

    /** @var string Currently in use: New, Assigned, Closed, Delivered, In Progress, Redirected */
    public $status;

    public $details;

    /** @var WorklogEntry[] */
    public $worklog = [];

    protected function __construct()
    {
    }

    /**
     * @param SimpleXMLElement $xml
     * @return static
     */
    public static function fromSimpleXml(SimpleXMLElement $xml)
    {
        $or = new static();
        $or->id      = (string) $xml->id;
        $or->title   = (string) $xml->title;
        $or->rep     = (string) $xml->rep;
        $or->repgrp  = (string) $xml->repgrp;
        $or->fe      = (string) $xml->fe;
        $or->ferep   = (string) $xml->ferep;
        $or->caller  = (string) $xml->caller;
        $or->status  = (string) $xml->status;
        $or->details = (string) $xml->details;
        foreach ($xml->worklog as $entry) {
            $or->worklog[] = WorklogEntry::fromSimpleXml($entry);
        }

        return $or;
    }
}
