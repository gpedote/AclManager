<?php
/**
 * Acl Manager
 *
 * A CakePHP Plugin to manage Acl
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Frédéric Massart - FMCorz.net
 * @copyright     Copyright 2011, Frédéric Massart
 * @link          http://github.com/FMCorz/AclManager
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('AclManager', 'AclManager.Lib');

class AclManagerController extends AclManagerAppController {

    protected $_authorizer = null;

/**
 * AclManager instance
 */
	public $AclManager;

/**
 * Constructor
 */
	public function __construct($request = null, $response = null) {
		parent::__construct($request, $response);
		$this->AclManager = new AclManager();
	}

/**
 * beforeFilter method
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();

		// Loading required Model
		$aros = Configure::read('AclManager.models');
		foreach ($aros as $aro) {
			$this->loadModel($aro);
		}

		// Pagination
 		$aros = Configure::read('AclManager.aros');
 		$paginatorSettings = array();
		foreach ($aros as $aro) {
			$limit = Configure::read("AclManager.{$aro}.limit");
			$limit = empty($limit) ? 4 : $limit;
			$paginatorSettings[$this->{$aro}->alias] = array(
				'recursive' => -1,
				'limit' => $limit
			);
		}
		$this->Paginator->settings = $paginatorSettings;
	}

/**
 * Delete everything
 *
 * @return void
 */
	public function drop() {
		$acoDelete = $this->Acl->Aco->deleteAll(array("1 = 1"));
		$aroDelete = $this->Acl->Aro->deleteAll(array("1 = 1"));
		if ($acoDelete && $aroDelete) {
			$this->Session->setFlash(__("Both ACOs and AROs have been dropped"), 'flash/success');
		} else {
			$this->Session->setFlash(__("Error while trying to drop ACOs/AROs"), 'flash/error');
		}
		$this->redirect(array("action" => "index"));
	}

/**
 * Delete all permissions
 *
 * @return void
 */
	public function dropPerms() {
		if ($this->Acl->Aro->Permission->deleteAll(array("1 = 1"))) {
			$this->Session->setFlash(__("Permissions dropped"), 'flash/success');
		} else {
			$this->Session->setFlash(__("Error while trying to drop permissions"), 'flash/error');
		}
		$this->redirect(array("action" => "index"));
	}

/**
 * Manage Permissions
 *
 * @return void
 */
	public function permissions() {

		// Saving permissions
		if ($this->request->is('post') || $this->request->is('put')) {
			$perms =  isset($this->request->data['Perms']) ? $this->request->data['Perms'] : array();
			foreach ($perms as $aco => $aros) {
				$action = str_replace(":", "/", $aco);
				foreach ($aros as $node => $perm) {
					list($model, $id) = explode(':', $node);
					$node = array('model' => $model, 'foreign_key' => $id);
					if ($perm == 'allow') {
						$this->Acl->allow($node, $action);
					} elseif ($perm == 'inherit') {
						$this->Acl->inherit($node, $action);
					} elseif ($perm == 'deny') {
						$this->Acl->deny($node, $action);
					}
				}
			}
		}

		$model = isset($this->request->params['named']['aro']) ? $this->request->params['named']['aro'] : null;
		if (!$model || !in_array($model, Configure::read('AclManager.aros'))) {
			$model = Configure::read('AclManager.aros');
			$model = $model[0];
		}

		$Aro = $this->{$model};
		$aros = $this->Paginator->paginate($Aro->alias);
		$permKeys = $this->_getKeys();

		// Build permissions info
		$perms = array();
		$parents = array();
		$acos = $this->Acl->Aco->find('all', array('order' => 'Aco.lft ASC', 'recursive' => 1));
		foreach ($acos as $key => $data) {
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
				$evaluate = $this->AclManager->evaluatePermissions($permKeys, array('id' => $aroId, 'alias' => $Aro->alias), $aco, $key);

				$perms[str_replace('/', ':', $acoNode)][$Aro->alias . ":" . $aroId . '-inherit'] = $evaluate['inherited'];
				$perms[str_replace('/', ':', $acoNode)][$Aro->alias . ":" . $aroId] = $evaluate['allowed'];
			}
		}

		$this->request->data = array('Perms' => $perms);
		$this->set('aroAlias', $Aro->alias);
		$this->set('aroDisplayField', $Aro->displayField);
        $this->set('aroPk', $Aro->primaryKey );
		$this->set(compact('acos', 'aros'));
	}


/**
 * Update ACOs
 * Sets the missing actions in the database
 *
 * @return void
 */
	public function updateAcos() {
		$count = 0;
		$knownAcos = $this->_getAcos();

		// Root node
		$aco = $this->_action(array(), '');
		if (!$rootNode = $this->Acl->Aco->node($aco)) {
			$rootNode = $this->_buildAcoNode($aco, null);
			$count++;
		}
		$knownAcos = $this->_removeActionFromAcos($knownAcos, $aco);

		// Loop around each controller and its actions
		$allActions = $this->AclManager->getActions();
		foreach ($allActions as $controller => $actions) {
			if (empty($actions)) {
				continue;
			}

			$parentNode = $rootNode;
			list($plugin, $controller) = pluginSplit($controller);

			// Plugin
			$aco = $this->_action(array('plugin' => $plugin), '/:plugin/');
			$aco = rtrim($aco, '/'); // Remove trailing slash
			$newNode = $parentNode;
			if ($plugin && !$newNode = $this->Acl->Aco->node($aco)) {
				$newNode = $this->_buildAcoNode($plugin, $parentNode);
				$count++;
			}
			$parentNode = $newNode;
			$knownAcos = $this->_removeActionFromAcos($knownAcos, $aco);

			// Controller
			$aco = $this->_action(array('controller' => $controller, 'plugin' => $plugin), '/:plugin/:controller');
			if (!$newNode = $this->Acl->Aco->node($aco)) {
				$newNode = $this->_buildAcoNode($controller, $parentNode);
				$count++;
			}
			$parentNode = $newNode;
			$knownAcos = $this->_removeActionFromAcos($knownAcos, $aco);

			// Actions
			foreach ($actions as $action) {
				$aco = $this->_action(array(
					'controller' => $controller,
					'action' => $action,
					'plugin' => $plugin
				));
				if (!$node = $this->Acl->Aco->node($aco)) {
					$this->_buildAcoNode($action, $parentNode);
					$count++;
				}
				$knownAcos = $this->_removeActionFromAcos($knownAcos, $aco);
			}
		}

		// Some ACOs are in the database but not in the controllers
		if (count($knownAcos) > 0) {
			$acoIds = Set::extract('/Aco/id', $knownAcos);
			$this->Acl->Aco->deleteAll(array('Aco.id' => $acoIds));
		}
		$this->Session->setFlash(sprintf(__("%d ACOs have been created/updated"), $count), 'flash/success');
		$this->redirect($this->request->referer());
	}

/**
 * Update AROs
 * Sets the missing AROs in the database
 *
 * @return void
 */
	public function updateAros() {

		// Debug off to enable redirect
		Configure::write('debug', 0);

		$count = 0;
		$type = 'Aro';

		// Over each ARO Model
		$objects = Configure::read("AclManager.aros");
		foreach ($objects as $object) {
			$Model = $this->{$object};
			$items = $Model->find('all');

			foreach ($items as $item) {
				$item = $item[$Model->alias];
				$Model->create();
				$Model->id = $item[$Model->primaryKey];
				try {
					$node = $Model->node();
				} catch (Exception $e) {
					$node = false;
				}

				// Node exists
				if ($node) {
					$parent = $Model->parentNode();
					if (!empty($parent)) {
						$parent = $Model->node($parent, $type);
					}
					$parent = isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null;

					// Parent is incorrect
					if ($parent != $node[0][$type]['parent_id']) {
						// Remove Aro here, otherwise we've got duplicate Aros
						// TODO: perhaps it would be nice to update the Aro with the correct parent
						$this->Acl->Aro->delete($node[0][$type]['id']);
						$node = null;
					}
				}

				// Missing Node or incorrect
				if (empty($node)) {

					// Extracted from AclBehavior::afterSave (and adapted)
					$parent = $Model->parentNode();
					if (!empty($parent)) {
						$parent = $Model->node($parent, $type);
					}
					$data = array(
						'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
						'model' => $Model->name,
						'foreign_key' => $Model->id
					);

					if ($alias = Configure::read("AclManager.aro_aliases.{$Model->name}")) {
						$data['alias'] = $item[$alias];
					}
					
					// Creating ARO
					$this->Acl->{$type}->create($data);
					$this->Acl->{$type}->save();
					$count++;
				}
			}
		}
		$this->Session->setFlash(sprintf(__("%d AROs have been created"), $count), 'flash/success');
		$this->redirect($this->request->referer());
	}

/**
 * Gets the action from Authorizer
 *
 * @return String
 */
	protected function _action($request = array(), $path = '/:plugin/:controller/:action') {
		$plugin = empty($request['plugin']) ? null : Inflector::camelize($request['plugin']) . '/';
		$params = array_merge(array('controller' => null, 'action' => null, 'plugin' => null), $request);
		$request = new CakeRequest(null, false);
		$request->addParams($params);
		$authorizer = $this->_getAuthorizer();
		return $authorizer->action($request, $path);
	}

/**
 * Build ACO node
 *
 * @return node
 */
	protected function _buildAcoNode($alias, $parentId = null) {
		if (is_array($parentId)) {
			$parentId = $parentId[0]['Aco']['id'];
		}
		$this->Acl->Aco->create(array('alias' => $alias, 'parent_id' => $parentId));
		$this->Acl->Aco->save();
		return array(array('Aco' => array('id' => $this->Acl->Aco->id)));
	}

/**
 * Returns all the ACOs including their path
 *
 * @return array
 */
	protected function _getAcos() {
		$acos = $this->Acl->Aco->find('all', array('order' => 'Aco.lft ASC', 'recursive' => -1));
		$parents = array();
		foreach ($acos as $key => $data) {
			$aco =& $acos[$key];
			$id = $aco['Aco']['id'];

			// Generate path
			if ($aco['Aco']['parent_id'] && isset($parents[$aco['Aco']['parent_id']])) {
				$parents[$id] = $parents[$aco['Aco']['parent_id']] . '/' . $aco['Aco']['alias'];
			} else {
				$parents[$id] = $aco['Aco']['alias'];
			}
			$aco['Aco']['action'] = $parents[$id];
		}
		return $acos;
	}

/**
 * Gets the Authorizer object from Auth
 *
 * @return authorizer object
 */
	protected function _getAuthorizer() {
		if (!is_null($this->_authorizer)) {
			return $this->_authorizer;
		}
		$authorzeObjects = $this->Auth->_authorizeObjects;
		foreach ($authorzeObjects as $object) {
			if (!$object instanceOf ActionsAuthorize) {
				continue;
			}
			$this->_authorizer = $object;
			break;
		}
		if (empty($this->_authorizer)) {
			$this->Session->setFlash(__("ActionAuthorizer could not be found"), 'flash/error');
			$this->redirect($this->referer());
		}
		return $this->_authorizer;
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
 * Returns an array without the corresponding action
 *
 * @return array
 */
	protected function _removeActionFromAcos($acos, $action) {
		foreach ($acos as $key => $aco) {
			if ($aco['Aco']['action'] == $action) {
				unset($acos[$key]);
				break;
			}
		}
		return $acos;
	}
}
