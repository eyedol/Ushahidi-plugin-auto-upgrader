<?php defined('SYSPATH') or die('No direct script access.');

class Upgrader_Controller extends Admin_Controller {
	
	private $db;
	
	private $upgrade;
	
	private $release;

	public function __construct() 
	{
		parent::__construct();

		$this->db = new Database();

		$this->template->this_page = 'upgrade';
		$this->upgrade = new Upgrade;
		$this->release = $this->upgrade->_fetch_core_release();

		// Don't show auto-upgrader when disabled.
		if (Kohana::config('config.enable_auto_upgrader') == FALSE)
		{
			die(Kohana::lang('ui_main.disabled'));
		}
	}

	public function index()
	{

	}

	public function status($step = 0)
	{

	}

	public function check_current_version()
	{

	}

	/**
	 * Execute SQL statement to upgrade the necessary tables.
	 *
	 * @param string - upgrade_sql - upgrade sql file
	 */
	private function _execute_upgrade_script($upgrade_sql) 
	{

	}

	/**
	 * Get the available sql update scripts from the 
	 * sql folder then upgrade necessary tables.
	 */
	private function _process_db_upgrade($dir_path)
	{

	}

	/**
	 * Gets the file name for the next db upgrade script
	 * 
	 * @return the db version.
	 */
	private function _get_next_db_upgrade()
	{

	}

	/**
	 * See if mysqldump exist, then detect its installed path.
	 *
	 * Most of the code here were borrowed from 
	 * @return (array) $paths - include mysql and mysqldump application's path.
	 */
	private function _detect_mysql()
	{

	}

	/**
	 * Backup database
	 *
	 * @param boolean - gzip - set to FALSE by default 
	 * 
	 * @return void or error message
	 */
	private function _do_db_backup( $gzip=FALSE ) 
	{

	}

	/**
	 * Get the operating environment Ushahidi is on.
	 *
	 * @return string
	 */
	private function _environment()
	{

	}

	/**
	 * Fetches the latest ushahidi release version number
	 *
	 * @return int or string
	 */
	private function _get_release_version()
	{

	}

	/**
	 * Checks version sequence parts
	 *
	 * @param string release_version - The version released.
	 * @param string version_ushahidi - The version of ushahidi installed.
	 *
	 * @return boolean
	 */
	private function _new_or_not($release_version=NULL,$version_ushahidi=NULL )
	{

	}
}