<?php

namespace Icinga\Module\Iet\Api;

use stdClass;

class ApiResult extends stdClass
{
    /** @var bool */
    protected $internalSuccess;

    /** @var string */
    protected $internalErrorMessage;

    public function succeeded(): bool
    {
        return $this->internalSuccess;
    }

    public function getInternalErrorMessage(): string
    {
        return $this->internalErrorMessage;
    }
}
