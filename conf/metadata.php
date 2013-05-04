<?php
/**
 * Options for the authsplit plugin
 *
 * @author Pieter Hollants <pieter@hollants.com>
 */

/* Define a custom "authtype" class that does not show authsplit */
if (!class_exists('setting_authtype_nosplit')) {
    class setting_authtype_nosplit extends setting_authtype {
        function initialize($default, $local, $protected) {
            parent::initialize($default, $local, $protected);

            $this->_choices = array_diff($this->_choices, array("authsplit"));
        }
    }
}

$meta['primary_authplugin']   = array('authtype_nosplit', '_cautionList' => array('plugin____authsplit____primary_authplugin' => 'danger'));
$meta['secondary_authplugin'] = array('authtype_nosplit', '_cautionList' => array('plugin____authsplit____secondary_authplugin' => 'danger'));
$meta['autocreate_users']     = array('onoff');
$meta['debug']                = array('onoff');
