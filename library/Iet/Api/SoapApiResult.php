<?php

namespace Icinga\Module\Iet\Api;

use RuntimeException;
use SimpleXMLElement;

use function sprintf;

class SoapApiResult extends ApiResult
{
    public static function fromResponse($response, string $soapNs, ?array $resultMap = null): SoapApiResult
    {
        self::assertHasStatus($response);
        self::assertHasAnyResult($response);
        $status = $response->ProcessOperationResult->Status;
        $simpleXmlResult = self::anyResultToSimpleXml($response->ProcessOperationResult->Result->any, $soapNs);
        if ($resultMap) {
            $result = new SoapApiResult();
            foreach ($resultMap as $resultProperty => $targetProperty) {
                $partial = $simpleXmlResult;
                foreach (explode('.', $resultProperty) as $key) {
                    $partial = $simpleXmlResult->$key;
                }
                $result->$targetProperty = self::simpleXmlToApiResult($partial);
            }
        } else {
            $result = self::simpleXmlToApiResult($simpleXmlResult);
        }

        if ($status === 'No Error') {
            $result->internalSuccess = true;
        } else {
            $result->internalSuccess = false;
            $first = current((array) $simpleXmlResult); // CreateOR vs CreateOR and such woes
            if (\is_string($first)) {
                $result->internalErrorMessage = "iET $status: $first";
            } elseif (isset($first->ErrorMessage)) {
                $result->internalErrorMessage = sprintf(
                    'iET %s: %s',
                    $status,
                    $first->ErrorMessage
                );
            } else {
                $result->internalErrorMessage = sprintf(
                    'iET %s (unsupported result): %s',
                    $status,
                    var_export($simpleXmlResult, 1)
                );
            }

            // TODO: return negative result?
            throw new RuntimeException($result->getInternalErrorMessage());
        }

        return $result;
    }

    protected static function simpleXmlToApiResult(SimpleXMLElement $element): ApiResult
    {
        $result = new ApiResult();
        foreach ((array) $element as $key => &$value) {
            if ($value instanceof SimpleXMLElement) {
                $result->$key = self::simpleXmlToApiResult($value);
            } else {
                $result->$key = $value;
            }
        }

        return $result;
    }

    protected static function anyResultToSimpleXml($result, string $ns): SimpleXMLElement
    {
        $resultWithNs = simplexml_load_string($result, null, null, $ns);
        if (empty($resultWithNs)) {
            return simplexml_load_string($result);
        }

        return $resultWithNs;
    }

    protected static function assertHasStatus($result)
    {
        if (! isset($result->ProcessOperationResult->Status)) {
            throw new RuntimeException(
                'ProcessOperation result has no ProcessOperationResult->Status: '
                . var_export($result, 1)
            );
        }
    }

    protected static function assertHasAnyResult($result)
    {
        if (! isset($result->ProcessOperationResult->Result->any)) {
            throw new RuntimeException(
                'ProcessOperation result has no ProcessOperationResult->Result->any: '
                . var_export($result, 1)
            );
        }
    }
}
