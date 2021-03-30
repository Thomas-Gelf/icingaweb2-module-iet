<?php

namespace Icinga\Module\Iet\ProvidedHook;

use Icinga\Application\Hook\TicketHook;
use Icinga\Module\Iet\Config;

class Ticket extends TicketHook
{
    public function getPattern()
    {
        return '/(?:Operational Request|iET issue) ([^:]+):(\d+)/';
    }

    /**
     * @param array $match
     * @return string
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function createLink($match)
    {
        $instance = $match[1];
        $host = Config::getSetting($instance, 'host');
        if (empty($host)) {
            return $match[0];
        }
        $or = $match[2];
        $url = sprintf('iet://%s/displayrecord?or=%d', $host, $or);
        $title = htmlspecialchars(mt('iet', 'Open Operational Request in iET'));
        $link = sprintf(
            '<a href="%s" target="_blank" title="%s">%d</a>',
            $url,
            $title,
            $or
        );

        return str_replace([
            "$instance:",
            $or,
        ], [
            '',
            $link,
        ], $match[0]);
    }
}
