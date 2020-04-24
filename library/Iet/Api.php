<?php

namespace Icinga\Module\Iet;

use RunTimeException;
use SoapClient;
use SoapFault;
use SoapVar;

class Api
{
    protected $ietNs;

    protected $wsdlUrl;

    protected $baseUrl;

    protected $user;

    protected $pass;

    protected $client;

    protected $verifyPeer = true;

    protected $sslCert;

    protected $sslKey;

    public function __construct($baseUrl, $user, $pass)
    {
        $this->baseUrl = $this->ietNs = rtrim($baseUrl, '/');
        $this->wsdlUrl = $this->baseUrl . '/IETWebservices.asmx?WSDL';
        $this->user = $user;
        $this->pass = $pass;
    }

    public function setNamespace($namespace)
    {
        $this->ietNs = $namespace;

        return $this;
    }

    public function setVerifyPeer($verifyPeer = true)
    {
        $this->verifyPeer = (bool) $verifyPeer;

        return $this;
    }

    public function setSslCert($cert, $key)
    {
        $this->sslCert = $cert;
        $this->sslKey = $key;

        return $this;
    }

    public function getDateTime()
    {
        return (string) $this->processOperation('GetDateTime')->DateTimeNow;
    }

    public function listCategories()
    {
        $pairs = [];

        foreach ($this->processOperation('GetCategories')->contents->content as $item) {
            $item = get_object_vars($item);
            if ($item['Active'] !== 'Y') {
                continue;
            }
            $pairs[(string) $item['iETid']] = $item['Name'];
        }

        return $pairs;
    }

    public function listReporters()
    {
        $pairs = [];

        foreach ($this->processOperation('GetReps')->Reps->Rep as $item) {
            $item = get_object_vars($item);
            $pairs[(string) $item['RepId']] = sprintf(
                '%s %s <%s>',
                $item['FirstName'],
                $item['LastName'],
                $item['EmailAddress']
            );
        }

        return $pairs;
    }

    public function listGroups()
    {
        $pairs = [];

        foreach ($this->processOperation('GetGroups')->contents->content as $item) {
            $item = get_object_vars($item);
            $pairs[(string) $item['Group']] = $item['Description'];
        }

        return $pairs;
    }

    public function listRepGroups($reporter)
    {
        $list = [];

        $content = $this->paramsToXml(['rep' => $reporter]);
        foreach ($this->processOperation('GetRepGroups', $content)->Groups as $item) {
            $item = get_object_vars($item->Group);
            $list[] = (string) $item['GroupName'];
        }

        return \array_combine($list, $list);
    }

    public function getReportersDefaultGroup($reporter)
    {
        $content = $this->paramsToXml(['rep' => $reporter]);

        return (string) $this->processOperation('GetDefGroupOfRep', $content)->rep->DefGroup;
    }

    public function listGroupsAssignable()
    {
        $pairs = [];
        foreach ($this->processOperation('GetGroupsAssignable')->Groups->Group as $item) {
            $item = get_object_vars($item);
            $pairs[(string) $item['Group']] = (string) $item['Description'];
        }

        return $pairs;
    }

    public function getAssignableGroupParents()
    {
        $pairs = [];
        foreach ($this->processOperation('GetGroupsAssignable')->Groups->Group as $item) {
            $item = get_object_vars($item);
            $parent = (string) $item['Parent'];
            if (strlen($parent) === 0) {
                $parent = null;
            }

            $pairs[(string) $item['Group']] = $parent;
        }

        return $pairs;
    }

    public function buildGroupsTree($onlyAssignable = true)
    {
        $assignments = $this->getAssignableGroupParents();
        $root = [];
        $all = [];
        foreach ($this->listGroups() as $name => $description) {
            $all[$name] = (object) [
                // 'name'        => $name,
                'description' => $description,
                'children'    => [],
            ];
        }

        foreach ($all as $name => $group) {
            $all[$name]->canBeAssigned = isset($assignments[$name]);
            if (isset($assignments[$name])) {
                $parent = $assignments[$name];

                if ($parent === null) {
                    $root[$name] = $group;
                } elseif (isset($all[$parent])) {
                    $all[$assignments[$name]]->children[$name] = $group;
                } else {
                    throw new RunTimeException('Got invalid assignment for ' . $name);
                }
            } else {
                $root[$name] = $group;
            }
        }

        if ($onlyAssignable) {
            $kill = [];
            foreach ($root as $name => $group) {
                if (empty($group->children) && ! $group->canBeAssigned) {
                    $kill[] = $name;
                }
            }

            foreach ($kill as $name) {
                unset($root[$name]);
            }
        }

        return $root;
    }

    public function listSourceSystems()
    {
        $pairs = [];
        // GetListOfSourceSystem -> SourceSystemList -> SourceSystem: SourceSystemId => SourceSystemName
        foreach ($this->processOperation('GetSourceSystemIdByName')->SourceSystem as $system) {
            $system = get_object_vars($system);
            $pairs[(string) $system['SourceSystemId']] = $system['SourceSystemName'];
            // Also available: $system['SourceSystemProcess']
        }

        return $pairs;
    }

    public function createOR($params)
    {
        $xml = "<root>\n" . $this->paramsToXml($params) . "</root>\n";
        $result = $this->processOperation('CreateOR', $xml, '1.1');

        return (string) $result->id;
    }

    public function updateOR($id, $params)
    {
        $params = (array) $params;
        $params['id'] = $id;
        $xml = "<OR>\n" . $this->paramsToXml($params) . "</OR>\n";
        $result = $this->processOperation('UpdateOR', $xml);

        return (string) $result->id;
    }

    public function addLinkToOR($id, $label, $url)
    {
        $params = (array) [
            'rfi_id'    => $id,
            'link_url'  => $url,
            'link_name' => $label,
        ];
        $xml = "<AddLinkToOR>\n" . $this->paramsToXml($params) . "</AddLinkToOR>\n";
        $this->processOperation('AddLinkToOR', $xml);
    }

    public function getOR($id)
    {
        $xml = "<OR>\n" . $this->paramsToXml(['id' => $id]) . "</OR>\n";

        return OperationalRequest::fromSimpleXml($this->processOperation('GetOR', $xml, '1.1')->OR);
    }

    protected function paramsToXml(array $params)
    {
        $xml = '';
        foreach ($params as $key => $value) {
            if (\strlen($value) === 0) {
                continue;
            }
            $xml .= "<$key>" . $this->escape($value) . "</$key>\n";
        }

        return $xml;
    }

    protected function escape($value)
    {
        return htmlspecialchars($value, ENT_COMPAT | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function fetchActiveCIsByCategory($category)
    {
        $data = '<root>'
             . "<c-gensym6><category>$category</category></c-gensym6>"
             . '<c-gensym8><active>Y</active></c-gensym8>'
             . '</root>';

        $pResult = $this->processOperation('GetCategoryCIs', $data, '2.0');

        $result = [];
        foreach ($pResult->contents->content as $entry) {
            $row = (object) get_object_vars($entry);
            if (property_exists($row, 'Attributes')) {
                $row->Attributes = @json_decode($row->Attributes);
            }
            $result[$row->iETid] = $row;
        }
        ksort($result);

        return array_values($result);
    }

    // Currently unused
    protected function normalizeSimpleXML($obj, &$result)
    {
        $data = $obj;
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $res = null;
                $this->normalizeSimpleXML($value, $res);

                if (($key === '@attributes') && ($key)) {
                    $result = $res;
                } elseif ($key === 'Attributes') {
                    $result[$key] = @json_decode($res);
                } else {
                    $result[$key] = $res;
                }
            }
        } else {
            $result = $data;
        }
    }

    public function processOperation($operation, $data = null, $version = '1.0')
    {
        $body = $this->makeOperation($operation, $data, $version);
        try {
            $result = $this->client()->ProcessOperation($body);
            $this->assertHasStatus($result);
            $this->assertHasAnyResult($result);
            $status = $result->ProcessOperationResult->Status;
            $result = $this->getAnyResult($result);

            if ($status !== 'No Error') {
                $first = current($result); // CreateOR vs CreateOR and such woes
                if (isset($first->ErrorMessage)) {
                    throw new RuntimeException(sprintf(
                        'iET %s: %s',
                        $status,
                        $first->ErrorMessage
                    ));
                } else {
                    throw new RuntimeException(sprintf(
                        'iET %s (unsupported result): %s',
                        $status,
                        var_export($result, 1)
                    ));
                }
            }

            return $result;
        } catch (SoapFault $e) {
            throw new RuntimeException('SOAP ERROR: ' . $e->getMessage());
        }
    }

    protected function makeOperation($operation, $data = null, $version = '2.0')
    {
        if ($data === null) {
            $data = "<root></root>";
        }

        // Temporarily ugly, there is something going wrong with 'any' otherwise
        $xml = '<ProcessOperation xmlns="http://www.iet-solutions.de/">'
             . '<identId>%s</identId>'
             . '<password>%s</password>'
             . '<prozess>%s</prozess>'
             . "<version>$version</version>"
             . '<processData>%s</processData>'
             . '</ProcessOperation>';

        return $this->createAnyXml(sprintf(
            $xml,
            $this->user,
            $this->pass,
            $operation,
            $data
        ));
    }

    protected function assertHasStatus($result)
    {
        if (! isset($result->ProcessOperationResult->Status)) {
            throw new RuntimeException(
                'ProcessOperation result has no ProcessOperationResult->Status: '
                . var_export($result, 1)
            );
        }
    }

    protected function assertHasAnyResult($result)
    {
        if (! isset($result->ProcessOperationResult->Result->any)) {
            throw new RuntimeException(
                'ProcessOperation result has no ProcessOperationResult->Result->any: '
                . var_export($result, 1)
            );
        }
    }

    protected function getAnyResult($result)
    {
        return simplexml_load_string(
            $result->ProcessOperationResult->Result->any,
            null,
            null,
            $this->ietNs
        );
    }

    protected function createAnyXml($string)
    {
        return new SoapVar($string, XSD_ANYXML);
    }

    protected function prepareStreamContext()
    {
        $params = ['ssl' => []];
        if (! $this->verifyPeer) {
            $params['ssl']['verify_peer'] = false;
            $params['ssl']['verify_peer_name'] = false;
        }

        if ($this->sslKey) {
            $params['ssl']['local_cert'] = $this->sslCert;
            $params['ssl']['local_pk'] = $this->sslKey;
        }

        return stream_context_create($params);
    }

    protected function client()
    {
        if ($this->client === null) {
            $options = array(
                'soap_version'   => SOAP_1_2,
                'exceptions'     => true,
                'encoding'       => 'utf8',
                'stream_context' => $this->prepareStreamContext(),
                // 'trace'        => 1,
            );

            $this->client = new SoapClient($this->wsdlUrl, $options);
        }

        return $this->client;
    }
}
