<?php
App::uses('AclManager', 'AclManager.Lib');
App::uses('HtmlHelper', 'View/Helper');

class AclHtmlHelper extends HtmlHelper {

/**
 * Helpers
 *
 * @var array
 */
    public $helpers = array('Session');

/**
 * AclManager instance
 */
    public $AclManager;

    public function __construct(View $View, $settings = array()) {
        parent::__construct($View, $settings);
        $this->AclManager = new AclManager();
    }


    public function link($title, $url = null, $options = array(), $confirmMessage = false) {
        if ($this->AclManager->checkPermission($url)) {
            return parent::link($title, $url, $options, $confirmMessage);
        } else {
            return null;
        }
    }

    public function checkPermissions($urls = null) {
        foreach ($urls as $url) {
            if ($this->AclManager->checkPermission($url)) {
                return true;
            }
        }
        return false;
    }

}