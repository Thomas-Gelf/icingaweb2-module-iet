<?php

namespace Icinga\Module\Iet\Api;

use gipfl\Json\JsonString;
use Icinga\Data\ConfigObject;
use Icinga\Module\Iet\Config;
use RuntimeException;

class RestApi implements ApiImplementation
{
    protected const METHOD_VERSIONS = [
        'AddLinkToOR'             => '11.0',
        'AttachFileTo_OR_SOR_RFI' => '11.0',
        'CreateOR'                => '11.1',
        'GetCategories'           => '11.0',
        'GetCategoryCis'          => '12.3',
        'GetCiById'               => '11.0',
        'GetCiRelations'          => '11.0',
        'GetDateTime'             => '11.1',
        'GetDefGroupOfRep'        => '11.0',
        'GetGroups'               => '11.0',
        'GetGroupsAssignable'     => '11.0',
        'GetIssues'               => '11.0',
        'GetOR'                   => '11.1',
        'GetRepGroups'            => '11.0',
        'GetReps'                 => '11.1',
        'GetSourceSystemIdByName' => '11.0',
        'UpdateOR'                => '11.0',
        'UpdateRFI'               => '11.0',
    ];

    /** @var SslContext */
    protected $sslContext;

    /** @var string */
    protected $baseUrl;
    protected $defaultRequestHeaders;

    public function __construct(string $baseUrl, string $authorizationHeaderLine, SslContext $sslContext)
    {
        $this->baseUrl = $baseUrl;
        $this->sslContext = $sslContext;
        $this->defaultRequestHeaders = [
            "Accept: application/json",
            "Authorization: $authorizationHeaderLine",
            "User-Agent: Icinga-iET/0.10",
        ];
    }

    protected function curlInit()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $this->sslContext->applyCurlOptions($curl);

        return $curl;
    }

    public function request(string $method, ?array $data = null): ApiResult
    {
        $body = $data ? JsonString::encode($data) : null;
        $curl = $this->curlInit();
        $headers = $this->defaultRequestHeaders;
        if ($body) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($body);
        } else {
            $headers[] = 'Content-Length: 0';
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $this->prepareUrl($method));
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            curl_close($curl);
            throw new RuntimeException('Request failed: ' . curl_error($curl));
        }
        curl_close($curl);

        return RestApiResult::fromResponse($result);
    }

    public static function fromConfig(string $name, ConfigObject $config, SslContext $sslContext): RestApi
    {
        $url = $config->get('url');
        if ($url === null) {
            Config::failMissing($name, 'url');
        }
        $token = $config->get('ietToken');
        if ($token === null) {
            Config::failMissing($name, 'ietToken');
        }

        // Hint: this is not a Bearer Token - for whatever reason 'Authorization: somekey' is required
        return new RestApi($url, $token, $sslContext);
    }

    protected function prepareUrl(string $method): string
    {
        return sprintf('%s/%s/%s', $this->baseUrl, urlencode($method), self::getApiVersionForMethod($method));
    }

    protected static function getApiVersionForMethod(string $method): string
    {
        if (isset(self::METHOD_VERSIONS[$method])) {
            return self::METHOD_VERSIONS[$method];
        }

        throw new RuntimeException("Unsupported method: $method");
    }
}
