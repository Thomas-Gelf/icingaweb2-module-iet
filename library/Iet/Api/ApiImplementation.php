<?php

namespace Icinga\Module\Iet\Api;

interface ApiImplementation
{
    public function request(string $method, ?array $data = null): ApiResult;
}
