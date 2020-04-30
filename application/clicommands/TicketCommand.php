<?php

namespace Icinga\Module\Iet\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Module\Iet\Config;
use Icinga\Module\Iet\IcingaCommandPipe;

class TicketCommand extends Command
{
    /**
     * Create an issue for the given Host or Service problem
     *
     * Use this as a NotificationCommand for Icinga
     *
     * USAGE
     *
     * icingacli iet ticket create [options]
     *
     * REQUIRED OPTIONS
     *
     *   --ietProcessName <process-name> iET Webservice Process Name,
     *                                   like "CreateNewIncident"
     *   --ietProcessVersion <version>   iET Webservice Process,
     *                                   defaults to "1.0"
     *   --state <state-name>            Icinga state
     *   --host <host-name>              Icinga Host name
     *
     * OPTIONAL
     *
     *   --ietInstance                   Refers to a configured instance
     *                                   (defaults to the first one)
     *   --service <service-name>        Icinga Service name
     *   --anyKey <value>                Becomes <anyKey>value</anyKey>
     *   --ack-author <author>           Username shown for acknowledgements,
     *                                   defaults to "iET (instanceName)"
     *   --no-acknowledge                Do not acknowledge Icinga problem
     *
     * FLAGS
     *   --verbose    More log information
     *   --trace      Get a full stack trace in case an error occurs
     *   --benchmark  Show timing and memory usage details
     */
    public function createAction()
    {
        $p = $this->params;

        $status      = $p->shiftRequired('state');
        if (\in_array($status, ['UP', 'OK'])) {
            // No existing issue, no problem, nothing to do
            return;
        }

        $instance = $this->params->shift('ietInstance');
        if ($instance === null) {
            $instance = Config::getDefaultName();
        }

        $ietProcess  = $p->shiftRequired('ietProcessName');
        $ietVersion  = $p->shift('ietProcessVersion', '1.0');
        $host        = $p->shiftRequired('host');
        $service     = $p->shift('service');
        $ackAuthor   = $p->shift('ack-author', "iET ($instance)");
        $description = $p->shiftRequired('description');

        $iet = Config::getApi($instance);

        $noAck = $p->shift('no-acknowledge');
        $params = [
            'project'     => $p->shiftRequired('project'),
            'issuetype'   => $p->shiftRequired('issuetype'),
            'summary'     => $p->shiftRequired('summary'),
            'description' => $description,
            'state'       => $status,
            'host'        => $host,
            'service'     => $service,
        ] + $p->getParams();

        $ietKey = $iet->processOperation($ietProcess, $params, $ietVersion);
        if ($noAck) {
            return;
        }

        $ackMessage = "iET issue $instance:$ietKey has been created";
        try {
            $cmd = new IcingaCommandPipe();
            if ($cmd->acknowledge($ackAuthor, $ackMessage, $host, $service)) {
                Logger::info("Problem has been acknowledged for $instance:$ietKey");
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
    }
}
