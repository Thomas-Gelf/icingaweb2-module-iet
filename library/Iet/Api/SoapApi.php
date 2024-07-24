<?php

namespace Icinga\Module\Iet\Api;

use Icinga\Data\ConfigObject;
use Icinga\Module\Iet\Config;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use RuntimeException;
use SoapClient;
use SoapFault;
use SoapVar;

class SoapApi implements ApiImplementation
{
    protected const METHOD_VERSIONS = [
        'AddLinkToOr'             => '1.0',
        'AttachFileTo_OR_SOR_RFI' => '1.0',
        'CreateOr'                => '1.1',
        'GetCategories'           => '1.0',
        'GetCategoryCis'          => '2.0',
        'GetCiById'               => '1.0',
        'GetCiRelations'          => '1.0',
        'GetDateTime'             => '1.0',
        'GetDefGroupOfRep'        => '1.0',
        'GetGroups'               => '1.0',
        'GetGroupsAssignable'     => '1.0',
        'GetIssues'               => '1.0',
        'GetOr'                   => '1.0',
        'GetRepGroups'            => '1.0',
        'GetReps'                 => '1.0',
        'GetSourceSystemIdByName' => '1.0',
        'UpdateOr'                => '1.0',
        'UpdateRFI'               => '1.0',
    ];

    protected const MAP_RESULT_PROPERTY = [
        'GetCategories'       => ['contents.content' => 'Categories'],
        'GetReps'             => ['Reps.Rep' => 'Rep'],
        'GetGroups'           => ['contents.content' => 'groups'],
        'GetRepGroups'        => ['Groups.Group' => 'Group'],
        'GetGroupsAssignable' => ['Groups.Group' => 'Group'],
    ];

    protected $ietNs;

    protected $wsdlUrl;

    protected $baseUrl;

    protected $user;

    protected $pass;

    protected $client;

    /** @var SslContext */
    protected $sslContext;

    public function __construct($baseUrl, $user, $pass, SslContext $sslContext)
    {
        $this->baseUrl = $this->ietNs = rtrim($baseUrl, '/');
        $this->wsdlUrl = $this->baseUrl . '/IETWebservices.asmx?WSDL';
        $this->user = $user;
        $this->pass = $pass;
        $this->sslContext = $sslContext;
    }

    public function request(string $method, ?array $data = null): ApiResult
    {
        $body = $this->processOperation($method, $data, self::getApiVersionForMethod($method));
        try {
            return SoapApiResult::fromResponse(
                $this->client()->ProcessOperation($body),
                $this->ietNs,
                self::MAP_RESULT_PROPERTY[$method] ?? null
            );
        } catch (SoapFault $e) {
            throw new RuntimeException('SOAP ERROR: ' . $e->getMessage());
        }
    }

    public static function fromConfig(string $name, ConfigObject $config, SslContext $sslContext): SoapApi
    {
        $url = $config->get('webservice');

        if ($url === null) {
            Config::failMissing($name, 'webservice');
        }

        $api = new SoapApi(
            $url,
            $config->get('username', 'ietws'),
            $config->get('password', 'ietws'),
            $sslContext
        );

        if ($ns = $config->get('namespace')) {
            $api->setNamespace($ns);
        }

        return $api;
    }

    public function setNamespace(string $namespace): void
    {
        $this->ietNs = $namespace;
    }

    protected static function getApiVersionForMethod(string $method): string
    {
        if (isset(self::METHOD_VERSIONS[$method])) {
            return self::METHOD_VERSIONS[$method];
        }

        throw new RuntimeException("Unsupported method: $method");
    }

    protected function makeXml(array $data): HtmlDocument
    {
        $result = new HtmlDocument();
        foreach ($data as $name => $value) {
            if ($value === null) {
                continue;
            }
            $result->add(Html::tag($name, $this->makeXml($value)));
        }

        return $result;
    }

    protected function processOperation($operation, ?array $data = null, $version = '1.0'): SoapVar
    {
        $xml = Html::tag('ProcessOperation', [
            'xmlns' => 'http://www.iet-solutions.de/',
        ], [
            Html::tag('identId', $this->user),
            Html::tag('password', $this->pass),
            Html::tag('prozess', $operation),
            Html::tag('version', $version),
            Html::tag('processData', Html::tag(
                'root',
                $data === null ? null : $this->makeXml($data)
            ))
        ]);

        return new SoapVar($xml->render(), XSD_ANYXML);
    }

    protected function client(): SoapClient
    {
        if ($this->client === null) {
            $options = array(
                'soap_version'   => SOAP_1_2,
                'exceptions'     => true,
                'encoding'       => 'utf8',
                'stream_context' => $this->sslContext->createStreamContext(),
                // 'trace'        => 1,
            );

            $this->client = new SoapClient($this->wsdlUrl, $options);
        }

        return $this->client;
    }
}
