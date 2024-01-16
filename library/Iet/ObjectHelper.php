<?php

namespace Icinga\Module\Iet;

use Icinga\Module\Icingadb\Model\Service as IcingaDbService;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;
use ipl\Orm\Model;

class ObjectHelper
{
    /** @var MonitoredObject|Model $object */
    protected $object;

    /** @var MonitoredObject|Model $object */
    protected $host;

    /** @var bool */
    protected $isService;

    /** @var string */
    protected $output;

    /** @var ?string */
    protected $serviceName;

    /** @var string */
    protected $hostName;

    /** @var ?string */
    protected $hostAddress;

    /** @var string */
    protected $stateName;

    /** @var array */
    protected $defaults;

    /**
     * @param MonitoredObject|Model $object
     */
    public function __construct($object)
    {
        $this->object = $object;
        if ($object instanceof MonitoredObject) {
            $this->isService = $object->getType() === 'service';
            if ($this->isService) {
                $this->host = $object->getHost();
                $this->host->fetch();
                $this->output = $object->service_output;
                $this->serviceName = $object->service_description;
            } else {
                $this->output = $object->host_output;
                $this->host = $object;
                $this->serviceName = null;
            }

            $this->hostAddress = $this->host->host_address;
            $this->hostName = $this->host->host_name;
        } else {
            if ($object instanceof IcingaDbService) {
                $this->host = $object->host;
                $this->serviceName = $object->name;
            } else {
                $this->host = $object;
                $this->serviceName = null;
            }
            $this->hostName = $this->host->name;
            $this->hostAddress = $this->host->address;
            $this->isService = $object instanceof IcingaDbService;
            $this->output = $object->state->output;
        }
        $this->stateName = $this->getStateName();
        $this->defaults = $this->getDefaults();
    }

    public function getServiceName()
    {
        return $this->serviceName;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    public function isIcingaDb(): bool
    {
        return $this->object instanceof Model;
    }

    public function getDefault($key, $default = null)
    {
        if (\array_key_exists($key, $this->defaults)) {
            return $this->defaults[$key] ?: $default;
        }

        return $default;
    }

    public function getDefaults(): array
    {
        $hostLabel = $this->hostName;
        if ($this->hostAddress) {
            if ($this->hostAddress !== $hostLabel) {
                $hostLabel = "$hostLabel (" . $this->hostAddress . ')';
            }
        }
        if ($this->isService) {
            $summary = sprintf(
                '%s on %s is %s',
                $this->serviceName,
                $hostLabel,
                $this->stateName
            );
        } else {
            $summary = sprintf(
                '%s is %s',
                $hostLabel,
                $this->stateName
            );
        }

        return [
            'state'   => $this->stateName,
            'title'   => $summary,
            'details' => $this->output,
            'icingahost'    => $this->hostName,
            'icingaservice' => $this->serviceName,
        ];
    }

    public function get($property)
    {
        return $this->object->$property;
    }

    protected function getStateName(): string
    {
        $object = $this->object;
        if ($object instanceof Model) {
            return strtoupper($object->state->getStateText());
        }

        if ($object->getType() === 'service') {
            return strtoupper(Service::getStateText($object->service_state));
        }

        return strtoupper(Host::getStateText($object->host_state));
    }
}
