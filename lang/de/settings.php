<?php
/**
 * english language file for authsplit plugin
 *
 * @author Pieter Hollants <pieter@hollants.com>
 */

$lang['plugin_settings_name']                   = 'Plugin zur geteilten Authentifizierung';

$lang['primary_authplugin']                     = 'Primäres Auth plugin zur Überprüfung von Loginnamen/Passwörter.';
$lang['secondary_authplugin']                   = 'Sekundäres Auth plugin zur Bereitstellung zusätzlicher Benutzerinformationen (Echter Name, E-Mail-Adressen, Gruppen). Dies wird üblicherweise "authplain" sein.';
$lang['autocreate_users']                       = 'Benutzerkonten automatisch anlegen? Hiermit wird das sekundäre Auth plugin, falls notwendig, ein Benutzerkonto mit Informationen vom primären Auth plugin anlegen.';
$lang['username_caseconversion']                = 'Groß-/Kleinschreibung von Benutzernamen korrigieren? Dies kann erforderlich sein, falls eins der Auth plugins Klein- und Großschreibung unterscheidet.';
$lang['username_caseconversion_o_None']         = 'Keine Änderung';
$lang['username_caseconversion_o_To uppercase'] = 'Großschreibung erzwingen';
$lang['username_caseconversion_o_To lowercase'] = 'Kleinschreibung erzwingen';

$lang['debug']                                  = 'Debug-Informationen anzeigen? Schalten Sie dies nur zur Problembehebung ein.';

//Setup VIM: ex: et ts=4 :
