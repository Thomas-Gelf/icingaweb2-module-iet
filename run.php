<?php

$this->provideHook('eventtracker/EventActions');
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('ticket');

$this->provideHook(
    'director/ImportSource',
    'Icinga\\Module\\Iet\\ImportSource\\ImportSourceIet'
);
