<?php

namespace Icinga\Module\Iet\Api;

use gipfl\Json\JsonString;

class RestApiResult extends ApiResult
{
    public static function fromResponse(string $response): ApiResult
    {
        $response = JsonString::decode($response);
        $result = new RestApiResult();
        if (isset($response->ResultCode) && $response->ResultCode === 'Error') {
            $result->internalSuccess = false;
            $result->internalErrorMessage = "iET Error: " . ($result->ResultMessage ?? '(Error without ResultMessage)');
        } else {
            $result->internalSuccess = false;
        }
        foreach ((array) $response as $key => $property) {
            $result->$key = $property;
        }

        return $result;
    }
}
