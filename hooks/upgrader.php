<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Upgrader Set up hooks
 */
class upgrader {

	/**
	 * Register main event add method
	 */
	public function __construct()
	{
		// Hook into routing system
		Event::add('system.pre_controller', array($this, 'add'));
	}

	/**
	 * Add events to the main Ushahidi application
	 */
	public function add() 
	{
		// Hook into the admin header nav bar
		Event::add('ushahidi_action.header_nav_bar', array($this, 
			'_upgrade_info'));
	}

	/**
	 * Add upgrade info
	 */
	public function _upgrade_info() 
	{
		if (Kohana::config('config.enable_ver_sync_warning') == TRUE)
		{
			$view = View::factory('admin/version_sync_view');
			$view->js = View::factory('admin/check_for_upgrades_js');
			$view->render(TRUE);
		}
	}
}

new upgrader;