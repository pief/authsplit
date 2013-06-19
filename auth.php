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
    protected $debug;

    /**
     * Show a debug message
     *
     * @param  string $message  The message to show
     * @param  int    $err      -1 for error, 0 for info, 1 for success
     * @param  int    $line     The line in $file that triggered the message.
     * @param  string $file     The filename that triggered the message.
     * @return void
     */
    protected function _debug($message, $err, $line, $file) {
        if (!$this->debug)
            return;
        msg($message, $err, $line, $file);
    }

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

        /* Create users automatically? */
        $this->autocreate_users = $this->getConf('autocreate_users', null);
        if ($this->autocreate_users === null) {
            msg(sprintf($this->getLang('nocfg'), 'autocreate_users'), -1);
            $this->success = false;
            return;
        }

        /* Show debug messages? */
        $this->debug = $this->getConf('debug', null);
        if ($this->debug === null) {
            msg(sprintf($this->getLang('nocfg'), 'debug'), -1);
            $this->success = false;
            return;
        }

        /* Of course, to modify login names actually BOTH auth plugins must
           support that. However, at this place we just consider the secondary
           auth plugin as otherwise admins can not add user accounts there in
           advance. */
        $this->cando['modLogin'] = $this->authplugins['secondary']->canDo('modLogin');

        /* To modify passwords, the primary auth plugin must support it */
        $this->cando['modPass'] = $this->authplugins['primary']->canDo('modPass');

        /* To add and delete user accounts, modify real names, email addresses,
           group memberships and groups, it is sufficient for the secondary
           auth plugin to support it. */
        foreach (array('addUser', 'delUser', 'modName', 'modMail', 'modGroups',
                       'getUsers', 'getUserCount', 'getGroups') as $cap) {
            $this->cando[$cap] = $this->authplugins['secondary']->canDo($cap);
        }

        /* Since we implement all auth plugin methods, the 'external' capability
           must be false */
        $this->cando['external'] = false;

        /* Whether we can do logout or not depends on the primary auth plugin */
        $this->cando['logout'] = $this->authplugins['primary']->canDo('logout');

        $msg = 'authsplit:__construct(): '.
               $this->authplugins['primary']->getPluginName().'/'.
               $this->authplugins['secondary']->getPluginName().' '.
               'combination can ';
        $parts = array(
            'addUser'      => 'add users',
            'delUser'      => 'delete users',
            'modLogin'     => 'modify login names',
            'modPass'      => 'modify passwords',
            'modName'      => 'modify real names',
            'modMail'      => 'modify E-Mail addresses',
            'modGroups'    => 'modify groups',
            'getUsers'     => 'get user list',
            'getUserCount' => 'get user counts',
            'getGroups'    => 'get groups',
            'logout'       => 'logout users',
        );
        foreach ($this->cando as $key => $value) {
            if ($this->cando[$key])
                $msg .= $parts[$key].', ';
        }
        $msg = rtrim($msg, ', ').'.';
        $this->_debug($msg, 1, __LINE__, __FILE__);
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
        if (!$this->authplugins['primary']->checkPass($user, $pass)) {
            $this->_debug(
                'authsplit:checkPass(): primary auth plugin\'s checkPass() '.
                'failed', -1, __LINE__, __FILE__
            );
            return false;
        }
        $this->_debug(
            'authsplit:checkPass(): primary auth plugin authenticated the '.
            'user successfully.', 1, __LINE__, __FILE__
        );

        /* Then make sure that the secondary auth plugin also knows about
           the user. */
        $userinfo = $this->authplugins['secondary']->getUserData($user);
        if (!$userinfo) {
            $this->_debug(
                'authsplit:checkPass(): secondary auth plugin\'s getUserData() '.
                'failed, seems user is yet unknown there.', -1,
                __LINE__, __FILE__
            );

            $this->_debug(
                'authsplit:checkPass(): autocreate_users is set to '.
                $this->autocreate_users.'.',
                $this->autocreate_users == 1 ? 1 : -1,
                __LINE__, __FILE__
            );

            /* Make sure automatic user creation is enabled */
            if (!$this->autocreate_users)
                return false;

            /* Make sure the secondary auth plugin can create user accounts */
            if (!$this->authplugins['secondary']->cando['addUser']) {
                msg(
                    sprintf(
                        $this->getLang('erraddusercap'),
                        $this->authplugins['secondary']->getPluginName()
                    ),
                    -1
                );
                return false;
            }

            /* Since auth plugins by definition must have a getUserData()
               method, we use the primary auth plugin's data to create a user
               account in the secondary auth plugin. */
            $params = $this->authplugins['primary']->getUserData($user);
            if (!$params) {
                msg(
                    sprintf(
                        $this->getLang('erradduserinfo'),
                        $this->authplugins['primary']->getPluginName()
                    ),
                    -1
                );
                return false;
            }
            $this->_debug(
                'authsplit:checkPass(): primary auth plugin\'s getUserData(): '.
                $this->_dumpUserData($params).'.', 1, __LINE__, __FILE__
            );

            /* Create the new user account */
            $result = $this->triggerUserMod(
                'create',
                array(
                    $user, $pass,
                    $params['name'], $params['mail'], $params['grps']
                )
            );
            if ($result === false || $result === null)
            {
                $this->_debug(
                    'authsplit:checkPass(): primary auth plugin\'s '.
                    'getUserData() could not supply data.', -1,
                    __LINE__, __FILE__
                );
                return false;
            }
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
        if (!$userinfo) {
            $this->_debug(
                'authsplit:checkPass(): primary auth plugin\'s getUserData() '.
                'failed, seems user is yet unknown there.', 1,
                __LINE__, __FILE__
            );
            return false;
        }

        $userinfo = $this->authplugins['secondary']->getUserData($user);
        if (!$userinfo) {
            $this->_debug(
                'authsplit:checkPass(): secondary auth plugin\'s getUserData() '.
                'failed, seems user is yet unknown there.', 1,
                __LINE__, __FILE__
            );
            return false;
        }
        $this->_debug(
            'authsplit:getUserData(): secondary auth plugin\'s getUserData(): '.
            $this->_dumpUserData($userinfo).'.', 1, __LINE__, __FILE__
        );

        return $userinfo;
    }

    /**
     * Returns a string representation of user data for debugging purposes
     *
     * @param  array  $user An array with user data
     * @return string
     */
    protected function _dumpUserData($user) {
        $msg = 'Name: "'.$user['name'].'", '.
               'Mail: "'.$user['mail'].'", ' .
               'Groups: ';
        foreach ($user['grps'] as $grp) {
            $msg .= '"'.$grp.'", ';
        }
        $msg = rtrim($msg, ', ');
        return $msg;
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
        /* Does the user not exist yet in the primary auth plugin and does it
           support creating users? */
        $userinfo = $this->authplugins['primary']->getUserData($user);
        if (!$userinfo && $this->authplugins['primary']->cando['addUser']) {
            $result = $this->authplugins['primary']->createUser(
                $user, $pass, $name, $email, ''
            );
            if ($result === false || $result === null) {
                $this->_debug(
                    'authsplit:createUser(): primary auth plugin\'s '.
                    'createUser() failed.', -1, __LINE__, __FILE__
                );
                return $result;
            }
            $this->_debug(
                'authsplit:createUser(): user created in primary auth plugin.',
                1, __LINE__, __FILE__
            );
        }

        /* We need to create the user in the secondary auth plugin in any case. */
        $result = $this->authplugins['secondary']->createUser(
            $user, '', $name, $mail, $grps
        );
        if ($result === false || $result === null) {
            $this->_debug(
                'authsplit:createUser(): secondary auth plugin\'s '.
                'createUser() failed.', -1, __LINE__, __FILE__
            );
            return $result;
        }

        $this->_debug(
            'authsplit:createUser(): user created in secondary auth plugin.',
            1, __LINE__, __FILE__
        );

        return true;
    }

    /**
     * Modify user data
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param   string $user    nick of the user to be changed
     * @param   array  $changes array of field/value pairs to be changed
     *                          (password will be clear text)
     * @return  bool
     */
    public function modifyUser($user, $changes) {
        if (!is_array($changes) || !count($changes))
            return true; // nothing to change

        foreach ($changes as $field => $value) {
            if ($field == 'pass') {
                /* Passwords must be changed in the primary auth plugin */
                $result = $this->authplugins['primary']->modifyUser(
                    $user,
                    array(
                        'pass' => $value
                    )
                );
                if (!$result) {
                    $this->_debug(
                        'authsplit:modifyUser(): primary auth plugin\'s '.
                        'modifyUser() failed.', -1, __LINE__, __FILE__
                    );
                    return false;
                }
            }
            elseif ($field == 'grps') {
                /* Groups are handled by the secondary auth plugin. */
                $result = $this->authplugins['secondary']->modifyUser(
                    $user,
                    array(
                        'grps' => $value
                    )
                );
                if (!$result) {
                    $this->_debug(
                        'authsplit:modifyUser(): secondary auth plugin\'s '.
                        'modifyUser() failed.', -1, __LINE__, __FILE__
                    );
                    return false;
                }
            }
            elseif ( ($field == 'login') || ($field == 'name') || ($field == 'mail') ) {
                /* If the primary auth plugin supports the update,
                   we'll try it there first. */
                if ($this->authplugins['primary']->canDo['mod' . ucfirst($field)]) {
                    $result = $this->authplugins['primary']->modifyUser(
                        $user, array(
                            $field => $value
                        )
                    );
                    if (!$result) {
                        $this->_debug(
                            'authsplit:modifyUser(): primary auth plugin\'s '.
                            'modifyUser() failed.', -1, __LINE__, __FILE__
                        );
                        return false;
                    }
                }

                /* Now in the secondary auth plugin. */
                $result = $this->authplugins['secondary']->modifyUser(
                    $user,
                    array(
                        $field => $value
                    )
                );
                if (!$result) {
                    $this->_debug(
                        'authsplit:modifyUser(): secondary auth plugin\'s '.
                        'modifyUser() failed.', -1, __LINE__, __FILE__
                    );
                    return false;
                }
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
        $result = $this->authplugins['secondary']->deleteUsers($users);
        if (!$result) {
            $this->_debug(
                'authsplit:deleteUsers(): secondary auth plugin\'s '.
                'deleteUsers() failed.', -1, __LINE__, __FILE__
            );
        }
        return $result;
    }

    /**
     * Bulk retrieval of user data
     *
     * Set getUsers capability when implemented
     *
     * @param   int   $start     index of first user to be returned
     * @param   int   $limit     max number of users to be returned
     * @param   array $filter    array of field/pattern pairs, null for no filter
     * @return  array list of userinfo (refer to getuseraccts for internal
     *                userinfo details)
     */
    public function retrieveUsers($start = 0, $limit = -1, $filter = null) {
        /* We're always interested in the users defined in the secondary auth
           plugin. */
        $result = $this->authplugins['secondary']->retrieveUsers(
            $start, $limit, $filter
        );
        if (!$result) {
            $this->_debug(
                'authsplit:retrieveUsers(): secondary auth plugin\'s '.
                'retrieveUsers() failed.', -1, __LINE__, __FILE__
            );
        }
        return $result;
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
        $result = $this->authplugins['secondary']->getUserCount($filter);
        if (!$result) {
            $this->_debug(
                'authsplit:getUserCount(): secondary auth plugin\'s '.
                'getUserCount() failed.', -1, __LINE__, __FILE__
            );
        }
        return $result;
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
        $result = $this->authplugins['secondary']->addGroup($group);
        if (!$result) {
            $this->_debug(
                'authsplit:addGroup(): secondary auth plugin\'s addGroup() '.
                'failed.', -1, __LINE__, __FILE__
            );
        }
        return $result;
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
        $result = $this->authplugins['secondary']->retrieveGroups($start, $limit);
        if (!$result) {
            $this->_debug(
                'authsplit:retrieveGroups(): secondary auth plugin\'s '.
                'retrieveGroups() failed.', -1, __LINE__, __FILE__
            );
        }
        return $result;
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
        $result = $this->authplugins['primary']->isCaseSensitive();
        $status = $result ? "Yes" : "No";
        $this->_debug(
            'authsplit:isCaseSensitive(): primary auth plugin\'s '.
            'isCaseSensitive(): '.$status.'.', 1, __LINE__, __FILE__
        );
        return $result;
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
        $result = $this->authplugins['primary']->cleanUser($user);
        $this->_debug(
            'authsplit:cleanUser(): primary auth plugin\'s '.
            'cleanUser("'.$user.'"): "'.$result.'".', 1, __LINE__, __FILE__
        );
        return $result;
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
        $result = $this->authplugins['secondary']->cleanGroup($group);
        $this->_debug(
            'authsplit:cleanGroup(): secondary auth plugin\'s '.
            'cleanGroup("'.$group.'"): "'.$result.'".', 1, __LINE__, __FILE__
        );
        return $result;
    }
}

// vim:ts=4:sw=4:et:
