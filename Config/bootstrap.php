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

/**
 * List of AROs (Class aliases)
 * Order is important! Parent to Children
 */
Configure::write('AclManager.aros', array('Group', 'User'));

/**
 * Aliases to write into ARO table
 */
Configure::write('AclManager.aro_aliases', array('Group' => 'name', 'User' => 'username'));

/**
 * Limit used to paginate AROs
 * Replace {alias} with ARO alias
 * Configure::write('AclManager.{alias}.limit', 3)
 */
// Configure::write('AclManager.Role.limit', 3);

/**
 * Routing Prefix
 * Set the prefix you would like to restrict the plugin to
 * @see Configure::read('Routing.prefixes')
 */
// Configure::write('AclManager.prefix', 'admin');

/**
 * Actions to ignore when looking for new ACOs
 * Format: A regex to match the action
 */
Configure::write('AclManager.ignoreActions', array('/isAuthorized/'));

/**
 * List of ARO models to load
 * Use only if AclManager.aros aliases are different than model name
 */
// Configure::write('AclManager.models', array('Group', 'Customer'));

/**
 * END OF USER SETTINGS
 */

Configure::write("AclManager.version", "1.2.5");
if (!is_array(Configure::read('AclManager.aros'))) {
	Configure::write('AclManager.aros', array(Configure::read('AclManager.aros')));
}
if (!is_array(Configure::read('AclManager.ignoreActions'))) {
	Configure::write('AclManager.ignoreActions', array(Configure::read('AclManager.ignoreActions')));
}
if (!Configure::read('AclManager.models')) {
	Configure::write('AclManager.models', Configure::read('AclManager.aros'));
}
