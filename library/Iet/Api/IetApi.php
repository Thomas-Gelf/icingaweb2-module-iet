<?php

namespace Icinga\Module\Iet\Api;

use Icinga\Module\Iet\OperationalRequest;
use RunTimeException;

class IetApi
{
    protected $api;

    public function __construct(ApiImplementation $apiImplementation)
    {
        $this->api = $apiImplementation;
    }

    public function getDateTime(): string
    {
        return (string) $this->api->request('GetDateTime')->DateTimeNow;
    }

    public function getIssues()
    {
        var_dump($this->api->request('GetIssues', ['rep' => 'XTG']));
    }

    public function listCategories(): array
    {
        $pairs = [];

        foreach ($this->api->request('GetCategories')->Categories as $item) {
            if ($item->Active !== 'Y') {
                continue;
            }
            $pairs[(string) $item->iETid] = $item->Name;
        }

        return $pairs;
    }

    public function listReporters(): array
    {
        $pairs = [];

        foreach ($this->api->request('GetReps')->Rep as $item) {
            $pairs[(string) $item->RepId] = sprintf(
                '%s %s <%s>',
                $item->FirstName,
                $item->LastName,
                $item->EmailAddress
            );
        }

        return $pairs;
    }

    public function listGroups(): array
    {
        $pairs = [];

        foreach ($this->api->request('GetGroups')->group as $item) {
            $pairs[$item->Group] = $item->Description;
        }

        return $pairs;
    }

    public function listRepGroups(string $reporter)
    {
        $list = [];

        foreach ($this->api->request('GetRepGroups', ['rep' => $reporter])->Group as $item) {
            $list[] = $item['GroupName'];
        }

        return \array_combine($list, $list);
    }

    public function getReportersDefaultGroup(string $reporter): string
    {
        // return 'INSE-UNOS';
        return (string) $this->api->request('GetDefGroupOfRep', ['rep' => $reporter])->rep->DefGroup;
    }

    public function listGroupsAssignable(): array
    {
        $pairs = [];
        foreach ($this->api->request('GetGroupsAssignable')->Group as $item) {
            $pairs[(string) $item['Group']] = (string) $item['Description'];
        }

        return $pairs;
    }

    public function getAssignableGroupParents(): array
    {
        $pairs = [];
        foreach ($this->api->request('GetGroupsAssignable')->Group as $item) {
            $parent = (string) $item['Parent'];
            if (strlen($parent) === 0) {
                $parent = null;
            }

            $pairs[(string) $item['Group']] = $parent;
        }

        return $pairs;
    }

    public function buildGroupsTree(bool $onlyAssignable = true): array
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

    public function listSourceSystems(): array
    {
        $pairs = [];
        // GetListOfSourceSystem -> SourceSystemList -> SourceSystem: SourceSystemId => SourceSystemName
        foreach ($this->api->request('GetSourceSystemIdByName')->SourceSystem as $system) {
            $pairs[(string) $system['SourceSystemId']] = $system['SourceSystemName'];
            // Also available: $system['SourceSystemProcess']
        }

        return $pairs;
    }

    public function createOR(array $params): string
    {
        $result = $this->api->request('CreateOR', $params);

        return (string) $result->id;
    }

    public function updateOR(string $id, array $params): string
    {
        $params['id'] = $id;
        $result = $this->api->request('UpdateOR', $params);

        return (string) $result->id;
    }

    public function addLinkToOR($id, $label, $url)
    {
        $params = [
            'rfi_id'    => $id,
            'link_url'  => $url,
            'link_name' => $label,
        ];
        $this->api->request('AddLinkToOR', $params);
    }

    public function attachFileToOR($id, $filename, $data)
    {
        $params = [
            'ID'         => $id,
            'Filename'   => $filename,
            'FileBase64' => base64_encode($data)
        ];

        $this->api->request('AttachFileTo_OR_SOR_RFI', $params);
    }

    public function getOR(string $id): OperationalRequest
    {
        return OperationalRequest::fromSimpleXml(
            $this->api->request('GetOR', ['id' => $id])->OR
        );
    }
}
