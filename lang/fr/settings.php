<?php
/**
 * French language file for authsplit plugin
 *
 * @author Schplurtz le Déboulonné <Schplurtz@laposte.net>
 */

$lang['plugin_settings_name']                   = "Greffon d'authentification Split";

$lang['primary_authplugin']                     = "Greffon primaire d'authentification qui vérifie les identifiants/mot de passe.";
$lang['secondary_authplugin']                   = "Greffon secondaire d'authentification qui fournit les informations d'utilisateur additionnelles (véritable nom, adresse de courriel, groupes). En général «authplain».";
$lang['autocreate_users']                       = "Créer les utilisateurs automatiquement ? Ceci indique au greffon d'authentification secondaire de créer les comptes avec les infos reçues du greffon primaire, si besoin.";
$lang['username_caseconversion']                = "Convertir les noms d'utilisateur en majuscule ou minuscule ? Cela peut être nécessaire si l'un des greffons d'authentification est sensible à la casse.";
$lang['username_caseconversion_o_None']         = 'Pas de changement';
$lang['username_caseconversion_o_To uppercase'] = 'En majuscule';
$lang['username_caseconversion_o_To lowercase'] = 'En minuscule';
$lang['debug']                                  = "Afficher les informations de debugage ? À n'utiliser que pour régler des problèmes.";

//Setup VIM: ex: et ts=4 :
