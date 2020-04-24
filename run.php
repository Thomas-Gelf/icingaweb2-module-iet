<?php

use Icinga\Module\Iet\ImportSource\ImportSourceIet;
use Icinga\Module\Iet\ImportSource\ImportSourceIetRaw;

$this->provideHook('eventtracker/EventActions');
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('ticket');

$this->provideHook('director/ImportSource', ImportSourceIet::class);
$this->provideHook('director/ImportSource', ImportSourceIetRaw::class);
