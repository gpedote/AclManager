<?php
/**
 * Acl Manager.
 *
 */

App::uses('Controller', 'Controller');
App::uses('ComponentCollection', 'Controller');
App::uses('AclComponent', 'Controller/Component');
App::uses('SessionComponent', 'Controller/Component');

/**
 * Generic acl methods
 *
 */
class AclManager extends Object {

/**
 * Contains instance of AclComponent
 *
 * @var AclComponent
 */
    public $Acl;

/**
 * Constructor
 */
    public function __construct($controller = null) {
        if (!$controller) {
            $controller = new Controller(new CakeRequest());
        }
        $collection = new ComponentCollection();
        $this->Acl = new AclComponent($collection);
        $this->Acl->startup($controller);
        $this->Aco = $this->Acl->Aco;
        $this->Session = new SessionComponent($collection);
        $this->controller = $controller;
    }

/**
 * Returns all the controllers from Cake and Plugins
 * Will only browse loaded plugins
 *
 * @return array('Controller1', 'Plugin.Controller2')
 */
    public function getControllers() {

        // Getting Cake controllers
        $objects = array('Cake' => array());
        $objects['Cake'] = App::objects('Controller');
        $unsetIndex = array_search("AppController", $objects['Cake']);
        if ($unsetIndex !== false) {
            unset($objects['Cake'][$unsetIndex]);
        }

        // Getting Plugins controllers
        $plugins = CakePlugin::loaded();
        foreach ($plugins as $plugin) {
            $objects[$plugin] = App::objects($plugin . '.Controller');
            $unsetIndex = array_search($plugin . "AppController", $objects[$plugin]);
            if ($unsetIndex !== false) {
                unset($objects[$plugin][$unsetIndex]);
            }
        }

        // Around each controller
        $return = array();
        foreach ($objects as $plugin => $controllers) {
            $controllers = str_replace("Controller", "", $controllers);
            foreach ($controllers as $controller) {
                if ($plugin !== "Cake") {
                    $controller = $plugin . "." . $controller;
                }
                if (App::import('Controller', $controller)) {
                    $return[] = $controller;
                }
            }
        }

        return $return;
    }

/**
 * Returns all the Actions found in the Controllers
 *
 * Ignores:
 * - protected and private methods (starting with _)
 * - Controller methods
 * - methods matching Configure::read('AclManager.ignoreActions')
 *
 * @return array('Controller' => array('action1', 'action2', ... ))
 */
    public function getActions() {
        $ignore = Configure::read('AclManager.ignoreActions');
        $baseMethods = get_class_methods('Controller');
        
        $controllers = $this->getControllers();
        $actions = array();
        foreach ($controllers as $controller) {
            list($plugin, $name) = pluginSplit($controller);

            $classMethods = get_class_methods($name . "Controller");
            $methods = array_diff($classMethods, $baseMethods);

            foreach ($methods as $key => $method) {
                if (strpos($method, "_", 0) === 0) {
                    unset($methods[$key]);
                    continue;
                }

                foreach ($ignore as $k){
                    if (preg_match($k, $method)) {
                        unset($methods[$key]);
                        break;
                    }
                }
            }
            $actions[$controller] = $methods;
        }
        return $actions;
    }

/**
 * Recursive function to find permissions avoiding slow $this->Acl->check().
 *
 * @return array 
 */
    public function evaluatePermissions($permKeys, $aro, $aco, $acoIndex, $acos) {
        $permissions = Set::extract("/Aro[model={$aro['alias']}][foreign_key={$aro['id']}]/Permission/.", $aco);
        $permissions = array_shift($permissions);

        $allowed = false;
        $inherited = false;
        $inheritedPerms = array();
        $allowedPerms = array();

        /**
         * Manually checking permission
         * Part of this logic comes from DbAcl::check()
         */
        foreach ($permKeys as $key) {
            if (!empty($permissions)) {
                if ($permissions[$key] == -1) {
                    $allowed = false;
                    break;
                } elseif ($permissions[$key] == 1) {
                    $allowedPerms[$key] = 1;
                } elseif ($permissions[$key] == 0) {
                    $inheritedPerms[$key] = 0;
                }
            } else {
                $inheritedPerms[$key] = 0;
            }
        }

        if (count($allowedPerms) === count($permKeys)) {
            $allowed = true;
        } elseif (count($inheritedPerms) === count($permKeys)) {
            if ($aco['Aco']['parent_id'] == null) {
                $acoNode = (isset($aco['Action'])) ? $aco['Action'] : null;
                $aroNode = array('model' => $aro['alias'], 'foreign_key' => $aro['id']);
                $allowed = $this->Acl->check($aroNode, $acoNode);
                $acos[$acoIndex]['evaluated'][$aro['id']] = array(
                    'allowed' => $allowed,
                    'inherited' => true
                );
            } else {
                /**
                 * Do not use Set::extract here. First of all it is terribly slow,
                 * besides this we need the aco array index ($key) to cache are result.
                 */
                foreach ($acos as $key => $a) {
                    if ($a['Aco']['id'] == $aco['Aco']['parent_id']) {
                        $parentAco = $a;
                        break;
                    }
                }

                // Return cached result if present
                if (isset($parentAco['evaluated'][$aro['id']])) {
                    return $parentAco['evaluated'][$aro['id']];
                }

                // Perform lookup of parent aco
                $evaluate = $this->evaluatePermissions($permKeys, $aro, $parentAco, $key, $acos);

                // Store result in acos array so we need less recursion for the next lookup
                $this->acos[$key]['evaluated'][$aro['id']] = $evaluate;
                $this->acos[$key]['evaluated'][$aro['id']]['inherited'] = true;

                $allowed = $evaluate['allowed'];
            }
            $inherited = true;
        }

        return array(
            'allowed' => $allowed,
            'inherited' => $inherited,
        );
    }

/**
 * Build permissions info
 *
 * @return array 
 */
    public function buildPermissionsInfo(&$acos = null, $Aro = null, $aros = null) {
        $perms = array();
        $parents = array();
        $permKeys = $this->_getKeys();

        if (empty($acos)) {
            $acos = $this->Acl->Aco->find('all', array('order' => 'Aco.lft ASC', 'recursive' => 1));
        }

        $ignore = Configure::read('AclManager.ignoreActions');
        foreach ($acos as $key => $data) {
            // Ignore actions
            $skip = false;
            foreach ($ignore as $regex){
                if (preg_match($regex, $acos[$key]['Aco']['alias'])) {
                    $skip = true;    
                }
            }
            if ($skip) {
                unset($acos[$key]);
                continue;
            }

            $aco =& $acos[$key];
            $aco = array('Aco' => $data['Aco'], 'Aro' => $data['Aro'], 'Action' => array());
            $id = $aco['Aco']['id'];

            // Generate path
            if ($aco['Aco']['parent_id'] && isset($parents[$aco['Aco']['parent_id']])) {
                $parents[$id] = $parents[$aco['Aco']['parent_id']] . '/' . $aco['Aco']['alias'];
            } else {
                $parents[$id] = $aco['Aco']['alias'];
            }
            $aco['Action'] = $parents[$id];

            

            // Fetching permissions per ARO
            $acoNode = $aco['Action'];
            foreach($aros as $aro) {
                $aroId = $aro[$Aro->alias][$Aro->primaryKey];
                $evaluate = $this->evaluatePermissions($permKeys, array('id' => $aroId, 'alias' => $Aro->alias), $aco, $key, $acos);

                $perms[str_replace('/', ':', $acoNode)][$Aro->alias . ":" . $aroId . '-inherit'] = $evaluate['inherited'];
                $perms[str_replace('/', ':', $acoNode)][$Aro->alias . ":" . $aroId] = $evaluate['allowed'];
            }
        }

        return $perms;
    }

/**
 * Build permissions info based on one aro
 *
 * @return array 
 */
    public function buildPermissionsAro($model = null, $aro = null) {
        if (empty($model) || empty($aro)) {
            return false;
        }

        $this->controller->loadModel($model);
        $Aro = $this->controller->{$model};
        $tmp = array();
        return $this->buildPermissionsInfo($tmp, $Aro, array(0 => $aro));
    }

/**
 * Returns permissions keys in Permission schema
 *
 * @see DbAcl::_getKeys()
 * @return array permission keys
 */
    protected function _getKeys() {
        $keys = $this->Acl->Aro->Permission->schema();
        $newKeys = array();
        $keys = array_keys($keys);
        foreach ($keys as $key) {
            if (!in_array($key, array('id', 'aro_id', 'aco_id'))) {
                $newKeys[] = $key;
            }
        }
        return $newKeys;
    }

/**
 * Loads all permissions of the authenticated user in Session
 *
 * @param array $user The user to check the authorization of.
 * @return boolean
 */
    protected function __loadPermissions($user = null) {
        if (empty($user)) {
            return false;
        }
        $model = key($user);

        $perms = $this->buildPermissionsAro($model, $user);
        $sessionPath = 'AclManager.permissions.' . $model . '.' . $user[$model]['id'];
        $this->Session->write($sessionPath, $perms);
        return true;
    }

/**
 * Check if the current logged user has access to the url
 *
 * @param array $url Url to check if the user has access based on the Acl
 * @param array $user The user to check the authorization of. If empty the user in the session will be used.
 * @return boolean
 */
    public function checkPermission($url = null, $user = null) {
        if (empty($url)) {
            return false;
        }

        if (empty($user)) {
            $user = $this->Session->read('Auth');
        }
        if (empty($user) || isset($user['redirect'])) {
            return false;
        }
        $model = key($user);

        $sessionPath = 'AclManager.permissions.' . $model . '.' . $user[$model]['id'];
        if (!$this->Session->check($sessionPath)) {
            $loaded = $this->__loadPermissions($user);
            if (!$loaded) {
                return true;
            }
        }

        $url = Router::url($url);

        $parsedUrl = Router::parse($url);

        $aco = '';
        if (!empty($parsedUrl['plugin'])) {
            $format = '%s:%s:%s:%s';
            $aco = sprintf($format, 
                'controllers',
                Inflector::camelize($parsedUrl['plugin']),
                Inflector::camelize($parsedUrl['controller']),
                $parsedUrl['action']
            );
        } else {
            $format = '%s:%s:%s';
            $aco = sprintf($format, 
                'controllers',
                Inflector::camelize($parsedUrl['controller']),
                $parsedUrl['action']
            );
        }
        
        $sessionPath = 'AclManager.permissions.' . $model . '.' . $user[$model]['id'];
        $path = $sessionPath . '.' . $aco . '.'. $model. ':' . $user[$model]['id'];

        if ($this->Session->check($path)) {
            return $this->Session->read($path);
        }
        return false;
    }

}