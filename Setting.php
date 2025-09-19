<?php

namespace dokuwiki\plugin\authsplit;

use dokuwiki\plugin\config\core\Setting\SettingAuthtype;

/**
 *  Defines a custom "authtype" class that does not show authsplit
 */
class Setting extends SettingAuthtype
{
    /** @inheritdoc */
    function initialize($default = null, $local = null, $protected = null)
    {
        parent::initialize($default, $local, $protected);

        $this->choices = array_diff($this->choices, ['authsplit']);
    }
}
