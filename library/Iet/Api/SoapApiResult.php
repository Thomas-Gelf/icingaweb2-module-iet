<?php

namespace Icinga\Module\Iet\Api;

use gipfl\Json\JsonString;
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
        // Pragmatic, simple way to convert the structure:
        $result = new SoapApiResult();
        $responseObject = JsonString::decode(JsonString::encode(
            self::anyResultToSimpleXml($response->ProcessOperationResult->Result->any, $soapNs)
        ));
        if ($resultMap) {
            foreach ($resultMap as $resultProperty => $targetProperty) {
                $partial = $responseObject;
                foreach (explode('.', $resultProperty) as $key) {
                    if (is_array($partial)) {
                        $newPartial = [];
                        foreach ($partial as $pEntry) {
                            $newPartial[] = $pEntry->$key;
                        }
                        $partial = $newPartial;
                    } else {
                        $partial = $partial->$key;
                    }
                }
                $result->$targetProperty = $partial;
            }
        } else {
            foreach ((array) $responseObject as $key => $property) {
                $result->$key = $property;
            }
        }

        if ($status === 'No Error') {
            $result->internalSuccess = true;
        } else {
            $result->internalSuccess = false;
            $first = current((array) $responseObject); // CreateOR vs CreateOR and such woes
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
                    var_export($responseObject, 1)
                );
            }

            // TODO: return negative result?
            throw new RuntimeException($result->getInternalErrorMessage());
        }

        return $result;
    }

    protected static function anyResultToSimpleXml($result, string $ns): SimpleXMLElement
    {
        $resultWithNs = simplexml_load_string($result, null, 0, $ns);
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
