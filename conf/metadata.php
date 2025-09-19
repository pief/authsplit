<?php
/**
 * Options for the authsplit plugin
 *
 * @author Pieter Hollants <pieter@hollants.com>
 */

$meta['primary_authplugin']       = array(\dokuwiki\plugin\authsplit\Setting::class, '_caution' => 'danger');
$meta['secondary_authplugin']     = array(\dokuwiki\plugin\authsplit\Setting::class, '_caution' => 'danger');
$meta['autocreate_users']         = array('onoff');
$meta['username_caseconversion']  = array('multichoice', '_choices' => array('None', 'To uppercase', 'To lowercase'));
$meta['debug']                    = array('onoff');
