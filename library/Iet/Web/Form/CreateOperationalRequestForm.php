<?php

namespace Icinga\Module\Iet\Web\Form;

use Icinga\Application\Logger;
use Icinga\Module\Iet\IcingaCommandPipe;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;

class CreateOperationalRequestForm extends BaseOperationalRequestForm
{
    /** @var  MonitoredObject */
    private $object;

    protected function addMessageDetails()
    {
        $this->addElement('text', 'title1', [
            'label'       => $this->translate('Title'),
            'required'    => true,
            'value'       => $this->getObjectDefault('title'),
            'description' => $this->translate(
                'Summary of this incident'
            ),
        ]);

        $this->addElement('textarea', 'details', array(
            'label'       => $this->translate('details'),
            'required'    => true,
            'value'       => \strip_tags($this->getObjectDefault('details')),
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ));
    }

    protected function ack($ietKey)
    {
        $object = $this->object;
        $host = $object->host_name;
        if ($object->getType() === 'service') {
            $service = $object->service_description;
        } else {
            $service = null;
        }

        $instance = $this->getValue('iet_instance');
        $ackMessage = "Operational Request $instance:$ietKey has been created";

        $cmd = new IcingaCommandPipe();
        if ($cmd->acknowledge("iET ($instance)", $ackMessage, $host, $service)) {
            Logger::info("Problem has been acknowledged for $ietKey");
        }
    }

    public function setObject(MonitoredObject $object)
    {
        $this->object = $object;

        return $this;
    }

    private function getObjectDefault($key)
    {
        $defaults = $this->getObjectDefaults();
        if (\array_key_exists($key, $defaults)) {
            return $defaults[$key];
        } else {
            return null;
        }
    }

    private function getObjectDefaults()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            $description = $object->service_output;
            $summary = sprintf(
                '%s on %s is %s',
                $object->service_description,
                $object->host_name,
                $this->getStateName()
            );
        } else {
            $description = $object->host_output;
            $summary = sprintf(
                '%s is %s',
                $object->host_name,
                $this->getStateName()
            );
        }

        $defaults = [
            'title'     => $summary,
            'details' => $description,
        ];

        return $defaults;
    }

    protected function getStateName()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            return \strtoupper(Service::getStateText($object->service_state));
        } else {
            return \strtoupper(Host::getStateText($object->host_state));
        }
    }
}
