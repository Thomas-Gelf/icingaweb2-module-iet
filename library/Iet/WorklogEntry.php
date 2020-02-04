<?php

namespace Icinga\Module\Iet;

use SimpleXMLElement;

class WorklogEntry
{
    public $topic;

    public $entry;

    public $enteredby;

    public $enteredate;

    protected function __construct()
    {
    }

    /**
     * @param SimpleXMLElement $xml
     * @return static
     */
    public static function fromSimpleXml(SimpleXMLElement $xml)
    {
        $entry = new static();
        $entry->topic      = (string) $xml->topic;
        $entry->entry      = (string) $xml->entry;
        $entry->enteredby  = (string) $xml->enteredby;
        $entry->enteredate = \strtotime((string) $xml->enteredate);

        return $entry;
    }
}
