<?php
/**
 * DokuWiki Plugin authsplit (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Pieter Hollants <pieter@hollants.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class auth_plugin_authsplit extends DokuWiki_Auth_Plugin {
    protected $authplugins;
    protected $autocreate_users;

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(); // for compatibility

        /* Load the config earlier than usual (we need it below) */
        $this->loadConfig();

        /* Load all referenced auth plugins */
        foreach (array('primary', 'secondary') as $type) {
            $settingName = $type.'_authplugin';
            $pluginName = $this->getConf($settingName);
            if (!$pluginName) {
                msg(sprintf($this->getLang('nocfg'), $settingName), -1);
                $this->success = false;
                return;
            }
            if ($pluginName != 'None') {
                $this->authplugins[$type] = plugin_load('auth', $pluginName);
                if (!$this->authplugins[$type]) {
                    msg(sprintf($this->getLang('pluginload'), $pluginName), -1);
                    $this->success = false;
                    return;
                }
            } else {
                $this->authplugins[$type] = null;
            }
        }

        /* One more config setting we'll need to take care of */
        $this->autocreate_users = $this->getConf('autocreate_users', null);
        if ($this->autocreate_users === null) {
            msg(sprintf($this->getLang('nocfg'), 'autocreate_users'), -1);
            $this->success = false;
            return;
        }

        /* Of course, to modify login names actually BOTH auth plugins must
           support that. However, at this place we just consider the secondary
           auth plugin as otherwise admins can not add user accounts there in
           advance. */
        $this->cando['modLogin'] = $this->authplugins['secondary']->cando['modLogin'];

        /* To modify passwords, the primary auth plugin must support it */
        $this->cando['modPass'] = $this->authplugins['primary']->cando['modPass'];

        /* To add and delete user accounts, modify real names, email addresses,
           group memberships and groups, it is sufficient for the secondary
           auth plugin to support it. */
        foreach (array('addUser', 'delUser', 'modName', 'modMail', 'modGroups',
                       'getUsers', 'getUserCount', 'getGroups') as $cap) {
            $this->cando[$cap] = $this->authplugins['secondary']->cando[$cap];
        }

        /* Since we implement all auth plugin methods, the 'external' capability
           must be false */
        $this->cando['external'] = false;

        /* Whether we can do logout or not depends on the primary auth plugin */
        $this->cando['logout'] = $this->authplugins['primary']->cando['logout'];
    }

    /**
     * Check user+password
     *
     * @param   string $user the user name
     * @param   string $pass the clear text password
     * @return  bool
     */
    public function checkPass($user, $pass) {
        /* First validate the username and password with the primary plugin. */
        if (!$this->authplugins['primary']->checkPass($user, $pass))
            return false;

        /* Then make sure that the secondary auth plugin also knows about
           the user. */
        $userinfo = $this->authplugins['secondary']->getUserData($user);
        if (!$userinfo) {
            /* Make sure automatic user creation is enabled */
            if (!$this->autocreate_users)
                return false;

            /* Make sure the secondary auth plugin can create user accounts */
            if (!$this->authplugins['secondary']->cando['addUser']) {
                msg(sprintf($this->getLang('erraddusercap'), $this->authplugins['secondary']->getPluginName()), -1);
                return false;
            }

            /* Since auth plugins by definition must have a getUserData()
               method, we use the primary auth plugin's data to create a user
               account in the secondary auth plugin. */
            $params = $this->authplugins['primary']->getUserData($user);
            if (!$params) {
                msg(sprintf($this->getLang('erradduserinfo'), $this->authplugins['primary']->getPluginName()), -1);
                return false;
            }

            /* Create the new user account */
            $result = $this->triggerUserMod('create', array(
                $user, $pass, $params['name'], $params['mail'], $params['grps']
            ));
            if ($result === false || $result === null)
                return false;

            msg($this->getLang('autocreated'), -1);
        }
        return true;
    }

    /**
     * Return user info
     *
     * Returned info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email address of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array containing user data or false
     */
    public function getUserData($user) {
        /* A user must be present in BOTH auth plugins. */
        $userinfo = $this->authplugins['primary']->getUserData($user);
        if (!$userinfo)
            return false;
        return $this->authplugins['secondary']->getUserData($user);
    }

    /**
     * Create a new User
     *
     * Returns false if the user already exists, null when an error
     * occurred and true if everything went well.
     *
     * The new user HAS TO be added to the default group by this
     * function!
     *
     * Set addUser capability when implemented
     *
     * @param  string     $user
     * @param  string     $pass
     * @param  string     $name
     * @param  string     $mail
     * @param  null|array $grps
     * @return bool|null
     */
    public function createUser($user, $pass, $name, $mail, $grps = null) {
        /* If the primary auth plugin supports creating users, we try to create
           the user there first. */
        if ($this->authplugins['primary']->cando['addUser']) {
            $result = $this->authplugins['primary']->createUser($user, $pass, $name, $email, '');
            if ($result === false || $result === null)
                return $result;
        }

        /* We need to create the user in the secondary auth plugin in any case. */
        $result = $this->authplugins['secondary']->createUser($user, '', $name, $mail, $grps);
        if ($result === false || $result === null)
            return $result;
        return true;
    }

    /**
     * Modify user data
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param   string $user    nick of the user to be changed
     * @param   array  $changes array of field/value pairs to be changed (password will be clear text)
     * @return  bool
     */
    public function modifyUser($user, $changes) {
        if (!is_array($changes) || !count($changes))
            return true; // nothing to change

        foreach ($changes as $field => $value) {
            if ($field == 'pass') {
                /* Passwords must be changed in the primary auth plugin */
                $result = $this->authplugins['primary']->modifyUser($user, array(
                    'pass' => $value
                ));
                if (!$result)
                    return false;
            }
            elseif ($field == 'grps') {
                /* Groups are handled by the secondary auth plugin. */
                $result = $this->authplugins['secondary']->modifyUser($user, array(
                    'grps' => $value
                ));
                if (!$result)
                    return false;
            }
            elseif ( ($field == 'user') || ($field == 'name') || ($field == 'mail') ) {
                /* If the primary auth plugin supports the update,
                   we'll try it there first. */
                if ($this->authplugins['primary']->canDo['mod' + ucfirst($field)]) {
                    $result = $this->authplugins['primary']->modifyUser($user, array(
                        $field => $value
                    ));
                    if (!$result)
                        return false;
                }

                /* Now in the secondary auth plugin. */
                $result = $this->authplugins['secondary']->modifyUser($user, array(
                    $field => $value
                ));
                if (!$result)
                    return false;
            }
        }

        return true;
    }

    /**
     * Delete one or more users
     *
     * Set delUser capability when implemented
     *
     * @param   array  $users
     * @return  int    number of users deleted
     */
    public function deleteUsers($users) {
        /* We do NOT attempt to delete the user with the primary auth plugin.
           Just because we don't want the account in DokuWiki any more, does not
           mean that there are no other services that depend on the account's
           existance in the primary auth source. */
        return $this->authplugins['secondary']->deleteUsers($users);
    }

    /**
     * Bulk retrieval of user data
     *
     * Set getUsers capability when implemented
     *
     * @param   int   $start     index of first user to be returned
     * @param   int   $limit     max number of users to be returned
     * @param   array $filter    array of field/pattern pairs, null for no filter
     * @return  array list of userinfo (refer getuseraccts for internal userinfo details)
     */
    public function retrieveUsers($start = 0, $limit = -1, $filter = null) {
        /* We're always interested in the users defined in the secondary auth plugin. */
        return $this->authplugins['secondary']->retrieveUsers($start, $limit, $filter);
    }

    /**
     * Return a count of the number of user which meet $filter criteria
     *
     * Set getUserCount capability when implemented
     *
     * @param  array $filter array of field/pattern pairs, empty array for no filter
     * @return int
     */
    public function getUserCount($filter = array()) {
        /* We're always interested in the users defined in the secondary auth plugin. */
        return $this->authplugins['secondary']->getUserCount($filter);
    }

    /**
     * Define a group
     *
     * Set addGroup capability when implemented
     *
     * @param   string $group
     * @return  bool
     */
    public function addGroup($group) {
        /* Groups are always defined in the secondary auth plugin. */
        return $this->authplugins['secondary']->addGroup($group);
    }

    /**
     * Retrieve groups
     *
     * Set getGroups capability when implemented
     *
     * @param   int $start
     * @param   int $limit
     * @return  array
     */
    public function retrieveGroups($start = 0, $limit = 0) {
        /* Groups are always defined in the secondary auth plugin. */
        return $this->authplugins['secondary']->retrieveGroups($start, $limit);
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     *
     * @return bool
     */
    public function isCaseSensitive() {
        /* The primary auth plugin dictates case-sensitivity of login names. */
        return $this->authplugins['primary']->isCaseSensitive();
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user) {
        /* The primary auth plugin dictates possible login name restrictions. */
        return $this->authplugins['primary']->cleanUser($user);
    }

    /**
     * Sanitize a given groupname
     *
     * This function is applied to any groupname that is given to
     * the backend and should also be applied to any groupname within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce groupname restrictions.
     *
     * Groupnames are to be passed without a leading '@' here.
     *
     * @param  string $group groupname
     * @return string the cleaned groupname
     */
    public function cleanGroup($group) {
        /* The secondary auth plugin dictates possible group names
           restrictions. */
        return $this->authplugins['secondary']->cleanGroup($group);;
    }
}

// vim:ts=4:sw=4:et:
