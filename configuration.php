<?php

/** @var \Icinga\Application\Modules\Module $this */

$this->providePermission(
    'iet/ticket/create',
    $this->translate('Allow to create tickets')
);
