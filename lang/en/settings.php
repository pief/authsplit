<?php
/**
 * english language file for authsplit plugin
 *
 * @author Pieter Hollants <pieter@hollants.com>
 */

$lang['plugin_settings_name']                   = 'Split authentication plugin';

$lang['primary_authplugin']                     = 'Primary auth plugin that checks login names/passwords.';
$lang['secondary_authplugin']                   = 'Secondary auth plugin that provides additional user data (real name, email address, groups). This will usually be "authplain".';
$lang['autocreate_users']                       = 'Create users automatically? This will tell the secondary auth plugin to create accounts with info from the primary auth plugin, if necessary.';
$lang['username_caseconversion']                = 'Convert usernames to upper- or lowercase? This might be necessary if one of the auth plugins is case-sensitive.';
$lang['username_caseconversion_o_None']         = 'No changes';
$lang['username_caseconversion_o_To uppercase'] = 'To uppercase';
$lang['username_caseconversion_o_To lowercase'] = 'To lowercase';
$lang['debug']                                  = 'Display debug information? Use this for troubleshooting purposes only.';

//Setup VIM: ex: et ts=4 :
