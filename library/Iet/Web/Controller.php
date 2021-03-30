<?php

namespace Icinga\Module\Iet\Web;

use ipl\Html\Html;
use gipfl\IcingaWeb2\CompatController;
use Exception;

class Controller extends CompatController
{
    protected function dump($what)
    {
        $this->content()->add(
            Html::tag('pre', null, print_r($what, true))
        );

        return $this;
    }

    protected function runFailSafe($callable)
    {
        try {
            if (is_array($callable)) {
                return call_user_func($callable);
            }
            if (is_string($callable)) {
                return call_user_func([$this, $callable]);
            }

            return $callable();
        } catch (Exception $e) {
            $this->content()->add($e->getMessage());
            $this->content()->add(Html::tag('pre', null, $e->getTraceAsString()));
            return false;
        }
    }
}
