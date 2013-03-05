<?php defined('SYSPATH') or die('No direct script access.');

class Upgrader_Controller extends Admin_Controller {
	
	protected $db;
	
	protected $upgrade;
	
	protected $release;

	public function __construct() 
	{
		parent::__construct();

		$this->db = new Database();

		$this->template->this_page = 'upgrade';
		$this->upgrade = new Update;
		$this->release = $this->upgrade->_fetch_core_release();

		// Don't show auto-upgrader when disabled.
		if (Kohana::config('config.enable_auto_upgrader') == FALSE)
		{
			die(Kohana::lang('ui_main.disabled'));
		}
	}

	public function index()
	{
		$this->template->content = new View('admin/upgrader/upgrader');

		$form_action = "";
		
		$this->template->content->title = Kohana::lang('ui_admin.upgrade_ushahidi');

		//$this->template->content->db_version =  Kohana::config('
		  //	  settings.db_version');
		$this->template->content->db_version = Kohana::config('settings.db_version');

		 //Setup and initialize form fields names
		$form = array 
		(
			'chk_db_backup_box' => ''
		);

		//check if form has been submitted
		if ( $_POST )
		{
			// For sanity sake, validate the data received from users.
			$post = Validation::factory(array_merge($_POST,$_FILES));

			// Add some filters
			$post->pre_filter('trim', TRUE);
			
			$post->add_rules('chk_db_backup_box', 'between[0,1]');
			
			if ($post->validate())
			{
				$this->upgrade->logger("STARTED UPGRADE\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~");
				$this->template->content = new View('admin/upgrader/upgrade_status');
				$this->themes->js = new View('admin/upgrader/upgrader_status_js');
				$this->themes->js->backup = $post->chk_db_backup_box;
				$this->template->content->title = Kohana::lang('ui_admin.upgrade_ushahidi_status');
				
				// Set submitted ftp credentials
				if (isset($post->ftp_server))
				{
					
					$hostname = explode(':', $post->ftp_server, 2);
					if (count($hostname) == 2 ) 
					{
						$this->session->set('hostname', $hostname[0]);
						$this->session->set('port', $hostname[1]);
					} 
					else 
					{ 
						$this->session->set('hostname', $post->ftp_server);
					}	
				}
				
				$this->session->set('username', $post->ftp_user_name);
				$this->session->set('password', $post->ftp_user_pass);
				
				Settings_Model::save_setting('ftp_server', $post->ftp_server);
				Settings_Model::save_setting('ftp_user_name', $post->ftp_user_name);
				
				// Log file location
				$this->themes->js->log_file = url::site(). "admin/upgrader/logfile?f=".$this->session->get('upgrade_session').".txt";
			}
			 // No! We have validation errors, we need to show the form again, with the errors
			else
			{
				$this->themes->js = new View('admin/upgrader/upgrader_js');
				
				// repopulate the form fields
				$form = arr::overwrite($form, $post->as_array());

				// populate the error fields, if any
				$errors = $post->errors('upgrade');
				$form_error = TRUE;
			}
		}
		else
		{
			$this->themes->js = new View('admin/upgrader/upgrader_js');
		}
		
		$this->template->content->ftp_server = Settings_Model::get_setting('ftp_server');
		$this->template->content->ftp_user_name = Settings_Model::get_setting('ftp_user_name');
		
		$this->template->content->form_action = $form_action;
		$this->template->content->current_version = Kohana::config('settings.ushahidi_version');
		$this->template->content->current_db_version = Kohana::config('settings.db_version');
		$this->template->content->environment = $this->_environment();
		$this->template->content->release_version = (is_object($this->release) == TRUE) ? $this->release->version : "";
		$this->template->content->release_db_version = (is_object($this->release) == TRUE) ? $this->release->version_db : "";
		$this->template->content->changelogs = (is_object($this->release) == TRUE) ? $this->release->changelog : array();
		$this->template->content->download = (is_object($this->release) == TRUE) ? $this->release->download : "";
		$this->template->content->critical = (is_object($this->release) == TRUE) ? $this->release->critical : "";
	}

	public function status($step = 0)
	{
		$this->template = "";
		$this->auto_render = FALSE;
		
		$url = $this->release->download;
		$working_dir = Kohana::config('upload.relative_directory')."/upgrade/";
		$zip_file = Kohana::config('upload.relative_directory')."/upgrade/ushahidi.zip";

		// These paths makes it possible for ftp to manipulate files
		$from = $this->upgrade->filesystem->trailingslashit($this->upgrade->filesystem->abspath())."media/uploads/upgrade/";
		$root = $this->upgrade->filesystem->trailingslashit($this->upgrade->filesystem->abspath());

		// Put website into maintenance mode
		if ($step == 0) 
		{
		 	$this->upgrade->logger("Putting Ushahidi into maintenance mode ");
		 	$this->upgrade->add_maintenance_file();

		 	if ($this->upgrade->success)
		 	{
		 		$this->upgrade->logger("Ushahidi Successfully put into maintenace mode");
		 		echo json_encode(array("status" => "success", "message" => 'Ushahidi Successfully put into maintenace mode'));
		 	} 
		 	else 
		 	{
		 	 	$this->upgrade->logger("Failed to put Ushahidi into maintenance mode");
		 	 	echo json_encode(array("status" => "error", "message" => 'Failed to put Ushahidi into maintenance mode'));
		 	}
		}

		if ($step == 1)
		{
			$this->upgrade->logger("Downloading latest version of ushahidi...Url");
			echo json_encode(array("status"=>"success", "message"=> 'Downloading latest version of ushahidi... url '));
		}

		if ($step == 2)
		{
			// Create the Directory if it doesn't exist
			if ( ! file_exists(DOCROOT."media/uploads/upgrade"))
			{
				mkdir(DOCROOT."media/uploads/upgrade");
				chmod(DOCROOT."media/uploads/upgrade",0777);
				$this->upgrade->logger("Creating Directory - ".DOCROOT."media/uploads/upgrade");
			}

			$latest_ushahidi = $this->upgrade->download_ushahidi($url);

			//download was successful
			if ($this->upgrade->success)
			{
				$this->upgrade->write_to_file($latest_ushahidi, $zip_file);
				$this->upgrade->logger("Successfully Downloaded. Unpacking ".$zip_file);
				echo json_encode(array("status"=>"success", "message"=> 'Successfully Downloaded'));
			}
			else
			{
				$this->upgrade->logger("** Failed downloading.\n\n");
				echo json_encode(array("status"=>"error", "message"=> 'Failed downloading. '));
			} 
		}

		if ($step == 3)
		{
			//extract compressed file
			$this->upgrade->unzip_ushahidi($zip_file, $working_dir);

			//extraction was successful
			if ($this->upgrade->success)
			{
				$this->upgrade->logger("Successfully Unpacked. Copying files...");
				echo json_encode(array("status"=>"success", "message"=>'Successfully Unpacked. Copying files...'));
			}
			else
			{
				$this->upgrade->logger("** Failed unpacking.\n\n");
				echo json_encode(array("status"=>"error", "message"=>'Failed unpacking.'));
			}
		}


		if ($step == 4)
		{

			//copy files
			$this->upgrade->copy_recursively($from."ushahidi/",$root);
			$this->upgrade->remove_old($working_dir.'ushahidi/upgrader_removed_files.txt', $root);
			
			// Clear out caches before new request
			Cache::instance()->delete_all();
			Kohana::cache_save('configuration', NULL, Kohana::config('core.internal_cache'));
			Kohana::cache_save('language', NULL, Kohana::config('core.internal_cache'));
			Kohana::cache_save('find_file_paths', NULL, Kohana::config('core.internal_cache'));
			Event::clear('system.shutdown', array('Kohana', 'internal_cache_save'));


			//copying was successful
			if ($this->upgrade->success)
			{
				$this->upgrade->logger("Successfully Copied. Upgrading Database...");
				echo json_encode(array("status"=>"success", "message"=>'Successfully Copied. Upgrading Database...'));
			}
			else
			{
				$this->upgrade->logger("** Failed copying files.\n\n");
				echo json_encode(array("status"=>"error", "message"=>'Failed copying files.'));
			}
		}


		// Database BACKUP + UPGRADE
		if ($step == 5)
		{
			// backup database.
			// is gzip enabled ?
			//$gzip = Kohana::config('config.output_compression');
			$gzip = TRUE;
			if (empty($error))
			{
				if (file_exists(DOCROOT."sql/"))
				{
					$this->_process_db_upgrade(DOCROOT."sql/");
				}

				$this->upgrade->logger("Database backup and upgrade successful.");
				echo json_encode(array("status"=>"success", "message"=>'Database backup and upgrade successful.'));
			}
			else
			{
				$this->upgrade->logger("** Failed backing up database.\n\n");
				echo json_encode(array("status"=>"error", "message"=>'Failed backing up database.'));
			}
		}


		// Database UPGRADE ONLY
		if ($step == 6)
		{
			if (file_exists(DOCROOT."sql/"))
			{
				//upgrade tables
				$this->_process_db_upgrade(DOCROOT."sql/");
				$this->upgrade->logger("Database upgrade successful.");
				echo json_encode(array("status"=>"success", "message"=>'Database upgrade successful.'));
			}
			else
			{

				$this->upgrade->logger("Database upgrade failed.");
				echo json_encode(array("status"=>"error", "message"=>'Database upgrade failed.'));
			}
		}

		// Delete downloaded files
		if ($step == 7)
		{
			$this->upgrade->logger("Deleting downloaded files...");
			echo json_encode(array("status"=>"success", "message"=>'Deleting downloaded files'));
				
		}

		if ($step == 8)
		{
			$this->upgrade->remove_recursively($root."media/uploads/upgrade/");

			if($this->upgrade->success)
			{ 
				$this->upgrade->logger("UPGRADE SUCCESSFUL");
				echo json_encode(array(
							"status"=>"success",
							"message"=> sprintf('UPGRADE SUCCESSFUL. View <a href="%s" target="_blank">Log File</a>', Url::base()."admin/upgrade/logfile?f=".$this->session['upgrade_session'].".txt")
				));
			}
			else
			{
				$this->upgrade->logger("UPGRADE WENT UNSUCCESSFUL");
				echo json_encode(array(
							"status"=>"error",
							"message"=> sprintf('UPGRADE UNSUCCESSFUL. View <a href="%s" target="_blank">Log File</a>', Url::base()."admin/upgrade/logfile?f=".$this->session['upgrade_session'].".txt")
				));
			}

			
		}

		//Remove maintenace file
		if ($step == 9)
		{
			$this->upgrade->remove_maintenance_file();

			if($this->upgrade->success) 
			{
				$this->upgrade->logger("Maintenace file successfully removed");
				echo json_encode(array("status"=>"success", "message"=>'Maintenance file successfully removed'));
			}
			else
			{
				$this->upgrade->logger("Maintenace file failed to removed");
				echo json_encode(array("status"=>"error", "message"=>'Maintenance file failed removed'));
			}
			unset($this->session['upgrade_session']);
		}
	}

	public function check_current_version()
	{
		//This is an AJAX call, so none of this fancy templating, just render the data
		$this->template = "";
		$this->auto_render = FALSE;
		
		// Disable profiler
		if (isset($this->profiler))
		{
			$this->profiler->disable();
		}
		
		$view = View::factory('admin/current_ushahidi_version');
		
		$upgrade = new Update;
		
		//fetch latest release of ushahidi
		$this->release = $upgrade->_fetch_core_release();
		
		if(!empty($this->release) )
		{
				$view->version = $this->_get_release_version();
				$view->critical = $this->release->critical;
		}
		$view->render(TRUE);
	}

	/**
	 * Execute SQL statement to upgrade the necessary tables.
	 *
	 * @param string - upgrade_sql - upgrade sql file
	 */
	private function _execute_upgrade_script($upgrade_sql) 
	{
		$upgrade_schema = @file_get_contents($upgrade_sql);

		// If a table prefix is specified, add it to sql
		$db_config = Kohana::config('database.default');
		$table_prefix = $db_config['table_prefix'];
		
		if ($table_prefix)
		{
			$find = array(
				'CREATE TABLE IF NOT EXISTS `',
				'INSERT INTO `',
				'INSERT IGNORE INTO `',
				'ALTER TABLE `',
				'UPDATE `',
				'FROM `',
				'LOCK TABLES `',
				'DROP TABLE IF EXISTS `',
				'RENAME TABLE `',
				' TO `',
				 // Potentially problematic. We use this to catch CREATE TABLE X LIKE Y, but could catch SELECT * WHERE X LIKE Y;
				' LIKE `',
			);
			
			$replace = array(
				'CREATE TABLE IF NOT EXISTS `'.$table_prefix,
				'INSERT INTO `'.$table_prefix,
				'INSERT IGNORE INTO `'.$table_prefix,
				'ALTER TABLE `'.$table_prefix,
				'UPDATE `'.$table_prefix,
				'FROM `'.$table_prefix,
				'LOCK TABLES `'.$table_prefix,
				'DROP TABLE IF EXISTS `'.$table_prefix,
				'RENAME TABLE `'.$table_prefix,
				' TO `'.$table_prefix,
				' LIKE `'.$table_prefix,
			);
			
			$upgrade_schema = str_replace($find, $replace, $upgrade_schema);
		}

		// Split by ; to get the sql statement for creating individual tables.
		$queries = explode( ';',$upgrade_schema );
		
		// get the database object.
	
		foreach ($queries as $query)
		{
			// Trim whitespace and make sure we're not running an empty query (for example from the new line after the last query.)
			$query = utf8::trim($query);
			if (!empty($query))
			{
				$result = $this->db->query($query);
			}
		}
			
		// Delete cache
		$cache = Cache::instance();
		$cache->delete(Kohana::config('settings.subdomain').'_settings');
	}

	/**
	 * Get the available sql update scripts from the 
	 * sql folder then upgrade necessary tables.
	 */
	private function _process_db_upgrade($dir_path)
	{
		ini_set('max_execution_time', 300);
		
		$file = $dir_path . $this->_get_next_db_upgrade();
		$this->upgrade->logger("Looking for update file: ".$file);
		while ( file_exists($file) AND is_file($file) )
		{
			$this->upgrade->logger("Database imported ".$file);
			$this->_execute_upgrade_script($file);
			
			// Get the next file
			$file = $dir_path . $this->_get_next_db_upgrade();
			$this->upgrade->logger("Looking for update file: ".$file);
		}
		return;
	}

	/**
	 * Gets the file name for the next db upgrade script
	 * 
	 * @return the db version.
	 */
	private function _get_next_db_upgrade()
	{
		// get the db version from the settings
		try
		{
			$query = Database::instance()->query('SELECT `value` FROM '.Kohana::config('database.default.table_prefix').'settings WHERE `key` = \'db_version\' LIMIT 1')->current();
			$version_in_db = $query->value;
		}
		catch (Exception $e)
		{
			$query = Database::instance()->query('SELECT `db_version` FROM '.Kohana::config('database.default.table_prefix').'settings LIMIT 1')->current();
			$version_in_db = $query->db_version;
		}
		
		// Just in case we get a DB fail.
		if ($version_in_db == NULL)
		{
			return FALSE;
		}
		
		// Special case for really old Ushahidi version
		if ($version_in_db < 11)
		{
			return 'upgrade.sql';
		}
		
		// Update DB
		$db_version = $version_in_db;

		$upgrade_to = $db_version + 1;
		
		return 'upgrade'.$db_version.'-'.$upgrade_to.'.sql';
	}

	/**
	 * See if mysqldump exist, then detect its installed path.
	 *
	 * @return (array) $paths - include mysql and mysqldump application's path.
	 */
	private function _detect_mysql()
	{
		$paths = array('mysql' => '', 'mysqldump' => '');
		
		//check for platform
		if (substr(PHP_OS,0,3) == 'WIN')
		{
			$result = mysql_query("SHOW VARIABLES LIKE 'basedir'");
			
			$mysql_install = mysql_fetch_array($result);

			if (is_array($mysql_install) AND sizeof($mysql_install)>0 )
			{
				$install_path = str_replace('\\', '/', $mysql_install[0]->Value);
				$paths['mysql'] = $install_path.'bin/mysql.exe';
				$paths['mysqldump'] = $install_path.'bin/mysqldump.exe';
			}
			else
			{
				$paths['mysql'] = 'mysql.exe';
				$paths['mysqldump'] = 'mysqldump.exe';
			}
		}
		else
		{
			if (function_exists('exec'))
			{
				$paths['mysql'] = @exec('which mysql');
				$paths['mysqldump'] = @exec('which mysqldump');
				
				if ( ! $paths['mysql'])
				{
					$paths['mysql'] = 'mysql';
				}
				
				if ( ! $paths['mysqldump'])
				{
					$paths['mysqldump'] = 'mysqldump';
				}
			}
			else
			{
				$paths['mysql'] = 'mysql';
				$paths['mysqldump'] = 'mysqldump';
			}
				
			return $paths; 
		}
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
		$mysql_path = $this->_detect_mysql();
		
		$database = Kohana::config('database');
		
		$error = '';
		$backup = array();
		$backup += $mysql_path;
		$backup['user'] = $database['default']['connection']['user'];
		$backup['password'] = $database['default']['connection']['pass'];
		$backup['host'] = $database['default']['connection']['host'];
		$backup['database'] = $database['default']['connection']['database'];
		$backup['date'] = time();
		$backup['filepath'] = preg_replace('/\//', '/', Kohana::config('upload.relative_directory'));
		$backup['filename'] = $backup['filepath'].'/backup_'.$this->session->get('upgrade_session').'.sql';
		
		if ($gzip)
		{
			$backup['filename'] = $backup['filename'].'.gz';
			$command = $mysql_path['mysqldump'].' --host="'.$backup['host'].'" --user="'.$backup['user'].'" --password="'.$backup['password'].'" --add-drop-table --skip-lock-tables '.$backup['database'].' | gzip > '.$backup['filename'];
		} 
		else
		{
			$backup['filename'] = $backup['filename'];
			$command = $mysql_path['mysqldump'].' --host="'.$backup['host'].'" --user="'.$backup['user'].'" --password="'.$backup['password'].'" --add-drop-table --skip-lock-tables '.$backup['database'].' > '.$backup['filename'];
		}
		
		$this->upgrade->logger("Backing up database to ".DOCROOT."media/uploads/".$backup['filename']);
		
		//Execute mysqldump command
		if (substr(PHP_OS, 0, 3) == 'WIN')
		{
			
			$writable_dir = $backup['filepath'];
			$tmpnam = $writable_dir.'/backup_script.sql';
			$fp = fopen($tmpnam, 'w');
			fwrite($fp, $command);
			fclose($fp);
			system($tmpnam.' > NUL', $error);
			unlink($tmpnam);
		}
		else
		{
			passthru($command, $error);
		}
		
		return $error;
	}

	/**
	 * Get the operating environment Ushahidi is on.
	 *
	 * @return string
	 */
	private function _environment()
	{
		$environment = "";
		$environment .= str_replace("/", "&nbsp;", preg_replace("/ .*$/", "", $_SERVER["SERVER_SOFTWARE"]));
		$environment .= ", PHP&nbsp;".phpversion();
		
		return $environment;
	}

	/**
	 * Fetches the latest ushahidi release version number
	 *
	 * @return int or string
	 */
	private function _get_release_version()
	{
		if (is_object($this->release))
		{
			$release_version = $this->release->version;
		
			$version_ushahidi = Kohana::config('settings.ushahidi_version');
			
			if ($this->_new_or_not($release_version,$version_ushahidi))
			{
				return $release_version;
			} 
			else 
			{
				return;
			}
		}
		return;
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
		if ($release_version AND $version_ushahidi)
		{
            
			// Split version numbers xx.xx.xx
			$remote_version = explode(".", $release_version);
			$local_version = explode(".", $version_ushahidi);

			// Check first part .. if its the same, move on to next part
			if (isset($remote_version[0]) AND isset($local_version[0])
				AND (int) $remote_version[0] > (int) $local_version[0])
			{
				return TRUE;
			}

			// Check second part .. if its the same, move on to next part
			if (isset($remote_version[1]) AND isset($local_version[1])
				AND (int) $remote_version[1] > (int) $local_version[1])
			{
				return TRUE;
			}

			// Check third part
			if (isset($remote_version[2]) AND (int) $remote_version[2] > 0)
			{
				if ( ! isset($local_version[2]))
				{
					return TRUE;
				}
				elseif( (int) $remote_version[2] > (int) $local_version[2] )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}
}