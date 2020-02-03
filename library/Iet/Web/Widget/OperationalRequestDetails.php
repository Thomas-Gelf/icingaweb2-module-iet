<?php

namespace Icinga\Module\Iet\Web\Widget;

use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Iet\OperationalRequest;
use ipl\Html\BaseHtmlElement;

class OperationalRequestDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'iet-or'
    ];

    protected $or;

    public function __construct(OperationalRequest $or)
    {
        $this->or = $or;
    }

    protected function assemble()
    {
        $this->add((new NameValueTable())->addNameValuePairs([
            $this->translate('ID') => $this->or->id,
        ]));
    }
}
