<?php

namespace Icinga\Module\Iet;

use SimpleXMLElement;
use stdClass;

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
    public static function fromSimpleXml(SimpleXMLElement $xml): WorklogEntry
    {
        $entry = new WorklogEntry();
        $entry->topic      = (string) $xml->topic;
        $entry->entry      = (string) $xml->entry;
        $entry->enteredby  = (string) $xml->enteredby;
        $entry->enteredate = \strtotime((string) $xml->enteredate);

        return $entry;
    }

    public static function fromStdClass(stdClass $object): WorklogEntry
    {
        $entry = new WorklogEntry();
        $entry->topic      = (string) $object->topic;
        $entry->entry      = (string) $object->entry;
        $entry->enteredby  = (string) $object->enteredby;
        $entry->enteredate = \strtotime((string) $object->enteredat); // hint: differs from SOAP (enteredate)?!

        return $entry;
    }
}
