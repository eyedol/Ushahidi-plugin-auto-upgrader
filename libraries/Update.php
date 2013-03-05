<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Upgrading  Library
 * Provides the necessary functions to do the automatic upgrade
 */

class Update {

	public $notices;
	public $errors;
	public $success;
	public $error_level;
	public $session;
	public $ftp;
	public $ftp_server;
	public $ftp_user_name;
	public $ftp_user_pass;
	public $filesystem;
	public $is_direct_method;

	public function __construct($session)
	{
		$this->log = array();
		$this->errors = array();
		$this->error_level = ini_get('error_reporting');
		$this->session = $session;
		$this->is_direct_method = false;

		if ( ! isset($this->session['upgrade_session']))
		{
			$this->session['upgrade_session'] = date("Y_m_d-H_i_s");
		}

		//initialize filesystem
		$this->init_filesystem( $this->session, $context = false );
	}

	/**
     * Fetches ushahidi from download.ushahidi.com
     *
     * @param String url-- download URL
     */
	public function download_ushahidi($url) 
	{
		$http_client = new HttpClient($url,30);
		$results = $http_client->execute();
		$this->logger("Starting to download the latest ushahidi build...");

		if ( $results)
		{
			$this->logger("Download of latest ushahidi went successful.");
			$this->success = TRUE;
			return $results;
		}

		else
		{
			$this->logger(sprintf("Upgrade failed: %d", $http_client->get_error_msg()));
			$this->errors[] = sprintf("Upgrade failed: %d", $http_client->get_error_msg());
			$this->success = false;
			return $results;
		}	
	}

	/**
		 * Initialises and connects the Ushahidi Filesystem Abstraction classes.
		 * This function will include the chosen transport and attempt connecting.
		 *
		 * @param array $args (optional) Connection args.
		 * @param string $context (optional) Context for get_filesystem_method(), See function declaration for more information.
		 * @return boolean false on failure, TRUE on success
		*/
	public function init_filesystem( $args = false, $context = false ) {

		$method = $this->get_filesystem_method($args, $context);

		if ( ! $method )
			return false;

		if ( ! class_exists("Ushahidi_Filesystem_$method") ) {
			$abstraction_file = sprintf(ROOT_DIR."libs".DIRECTORY_SEPARATOR."Ushahidi_FileSystem_%s.php",$method);
			if ( ! file_exists($abstraction_file) ) { 
				return;
			}

			require_once($abstraction_file);
		}
		
		// Determine if the method being used is direct
		$this->is_direct_method = strtolower($method) == 'direct' ? TRUE : false;

		$method = "Ushahidi_FileSystem_$method";

		$this->filesystem = new $method($args);

		//Define the timeouts for the connections. Only available after the construct is called to allow for per-transport overriding of the default.
		if ( ! defined('FS_CONNECT_TIMEOUT') )
			define('FS_CONNECT_TIMEOUT', 30);
		if ( ! defined('FS_TIMEOUT') )
			define('FS_TIMEOUT', 30);

		if ( count($this->filesystem->errors) > 0 )
			return false;

		if ( !$this->filesystem->connect() )
			return false; //There was an error connecting to the server.

		// Set the permission constants if not already set.
		if ( ! defined('FS_CHMOD_DIR') )
			define('FS_CHMOD_DIR', 0755 );
		if ( ! defined('FS_CHMOD_FILE') )
			define('FS_CHMOD_FILE', 0644 );

		return TRUE;
	}

	/**
		 * Determines which Filesystem Method to use.
		 * The priority of the Transports are: Direct, SSH2, FTP PHP Extension, FTP Sockets (Via Sockets class, or fsockopen())
		 *
		 * Note that the return value of this function can be overridden in 2 ways
		 *  - By defining FS_METHOD in your <code>wp-config.php</code> file
		 *  - By using the filesystem_method filter
		 * Valid values for these are: 'direct', 'ssh', 'ftpext' or 'ftpsockets'
		 * Plugins may also define a custom transport handler, See the WP_Filesystem function for more information.
 	 *
		 * @since 2.5.0
		 *
		 * @param array $args Connection details.
		 * @param string $context Full path to the directory that is tested for being writable.
		 * @return string The transport to use, see description for valid return values.
		 */
	public function get_filesystem_method($args = array(), $context = false) {
		$method = defined('FS_METHOD') ? FS_METHOD : false; //Please ensure that this is either 'direct', 'ssh', 'ftpext' or 'ftpsockets'

		if ( ! $method && function_exists('getmyuid') && function_exists('fileowner') ){
			if ( !$context )
				$context = DOCROOT;
			$context = $this->trailingslashit($context);
			$temp_file_name = $context . 'temp-write-test-' . time();
			$temp_handle = @fopen($temp_file_name, 'w');
			if ( $temp_handle ) {
				if ( getmyuid() == @fileowner($temp_file_name) )
					$method = 'direct';
				@fclose($temp_handle);
				@unlink($temp_file_name);
			}
			}

		if ( ! $method && isset($args['connection_type']) && 'ssh' == $args['connection_type'] && extension_loaded('ssh2') && function_exists('stream_get_contents') ) $method = 'ssh2';
		if ( ! $method && extension_loaded('ftp') ) $method = 'ftpext';
		if ( ! $method && ( extension_loaded('sockets') || function_exists('fsockopen') ) ) $method = 'ftpsockets'; //Sockets: Socket extension; PHP Mode: FSockopen / fwrite / fread
		
		return ucfirst($method);
	}

	/**
 	 * Unzip the file.
 	 *
 	 * @param String zip_file-- the zip file to be extracted.
 	 * @param String destdir-- destination directory
 	 */

	public function unzip_ushahidi($zip_file, $destdir)
	{
		$archive = new Pclzip($zip_file);
		$this->log[] = sprintf("Unpacking %s ",$zip_file);

		if (@$archive->extract(PCLZIP_OPT_PATH, $destdir) == 0)
		{
			$this->errors[] = sprintf('Extractions failed to execute successfully',$archive->errorInfo(TRUE) ) ;
			return false;
		}

		$this->log[] = sprintf("Unpacking went successful");
		$this->success = TRUE;
		return TRUE;
	}

	/**
 	 * Write the zip file to a file.
 	 *
 	 * @param String zip_file-- the zip file to be written.
 	 * @param String dest_file-- the file to write.
	 */
	public function write_to_file($zip_file, $dest_file)
	{
		$handler = fopen( $dest_file,'w');
		$fwritten = fwrite($handler,$zip_file);
		$this->log[] = sprintf("Writting to a file ");
		if( !$fwritten ) {
			$this->errors[] = sprintf('Writing to zip file failed',$dest_file);
			$this->success = false;
			return false;
		}
		fclose($handler);
		$this->success = TRUE;
		$this->log[] = sprintf("Zip file successfully written to a file ");
		return TRUE;
	}

	/**
	 * Fetch latest ushahidi version from a remote instance then
	 * compare it with local instance version number.
	 *
	 * @param float ushahidi_version The version number of the currently running
	 * ushahidi.
	 */
	public function _fetch_core_release($ushahidi_version)
	{
		// Current Version
		$current = urlencode($ushahidi_version);

		// Extra Stats
		$url = urlencode(preg_replace("/^https?:\/\/(.+)$/i","\\1",
						Url::base()));
		$ip_address = (isset($_SERVER['REMOTE_ADDR'])) ?
		urlencode($_SERVER['REMOTE_ADDR']) : "";

		$version_url = "http://version.ushahidi.com/2/?v=".$current.
		"&u=".$url."&ip=".$ip_address;

		preg_match('/({.*})/', @file_get_contents($version_url), $matches);

		$version_json_string = false;
		if(isset($matches[0]))
		{
			$version_json_string = $matches[0];
		}

		// If we didn't get anything back...
		if ( ! $version_json_string )
		{
			return ;
		}

		$version_details = json_decode($version_json_string);
		return $version_details;
	}

	/**
	 * Log Messages To File
	 */
	public function logger($message)
	{
		$filter_crlf = array("\n", "\r");
		$message = date("Y-m-d H:i:s")." : ".$message;
		$mesg = str_replace($filter_crlf,'',$message);
		$mesg .= "\n";
		//$logfile = DOCROOT."application/logs/".$this->session['upgrade_session'].".txt";
		$logfile = DOCROOT."application/logs/upgrade_log.txt";
		$logfile = fopen($logfile, 'a+');
		fwrite($logfile, $mesg);
		fclose($logfile);
	}

	/**
	 * Copies a directory from one location to another via the Ushahidi Filesystem Abstraction.
	 *
	 *
	 * @param string $from source directory
	 * @param string $to destination directory
	 * @param array $skip_list a list of files/folders to skip copying
	 * @return boolean on failure, TRUE on success.
	 */
	public function copy_recursively($from, $to, $skip_list = array() ) {
		$dirlist = $this->filesystem->dirlist($from);
		$from = $this->filesystem->trailingslashit($from);
		$to = $this->filesystem->trailingslashit($to);

		$skip_regex = '';
		foreach ( (array)$skip_list as $key => $skip_file )
			$skip_regex .= preg_quote($skip_file, '!') . '|';

		if ( !empty($skip_regex) )
			$skip_regex = '!(' . rtrim($skip_regex, '|') . ')$!i';

		foreach ( (array) $dirlist as $filename => $fileinfo ) {
			if ( !empty($skip_regex) )
				if ( preg_match($skip_regex, $from . $filename) )
					continue;

			if ( 'f' == $fileinfo['type'] ) {
				if ( ! $this->filesystem->copy($from . $filename, $to . $filename, TRUE, FS_CHMOD_FILE) ) {
					// If copy failed, chmod file to 0644 and try again.

					$this->filesystem->chmod($to . $filename, 0644);

					if ( ! $this->filesystem->copy($from . $filename, $to . $filename, TRUE, FS_CHMOD_FILE) ) 
					{
						$this->logger(sprintf("Could not copy %s ", $to . $filename));
						$this->success = false;
						return false; 
					} else {
						$this->success = TRUE;
						$this->logger("Copied to ".$to . $filename);
						//Turn on error reporting again
						error_reporting($this->error_level);
					}

				} else {
					$this->success = TRUE;
					$this->logger("Copied to ".$to . $filename);
					//Turn on error reporting again
					error_reporting($this->error_level);
				}
			} elseif ( 'd' == $fileinfo['type'] ) {
				if ( !$this->filesystem->is_dir($to . $filename) ) {
					if ( !$this->filesystem->mkdir($to . $filename, FS_CHMOD_DIR) )
						$this->logger("** Failed creating directory ".$to . $filename.". It might already exist.");
					
				}
				$this->copy_recursively($from . $filename, $to . $filename, $skip_list);
			}
		}
		return TRUE;
	}

	/**
	 * Delete files that no longer exist in latest version
	 **/
	public function remove_old($file, $base_directory)
	{
		$root = $this->filesystem->trailingslashit($this->filesystem->abspath());
		$this->filesystem->chdir($root);

		$old_files = file($file, FILE_IGNORE_NEW_LINES);
		if(is_array($old_files)) { 
			foreach ($old_files as $old_file)
			{
				$ftp_filename = str_replace($root,"",$old_file);

				// Skip removed config files
				if (stripos($old_file,'application/config/') !== FALSE) continue;

				if (is_file($old_file))
				{
					// Turn off error reporting temporarily
					error_reporting(0);
					$result = $this->filesystem->delete($ftp_filename);
					if ($result)
					{
						$this->success = TRUE;
						$this->logger("Removed ".$old_file);
						//Turn on error reporting again
						error_reporting($this->error_level);
					}
					else
					{
						$this->success = false;
						$this->logger("** Failed removing ".$old_file);
						//Turn on error reporting again
						error_reporting($this->error_level);
						return false;
					}
				}
				elseif(is_dir($old_file))
				{
					error_reporting(0);
					$result = $this->filesystem->delete($ftp_filename,TRUE);
					if ($result)
					{
						$this->success = TRUE;
						$this->logger("Removed ".$old_file);
						//Turn on error reporting again
						error_reporting($this->error_level);
					}
					else
					{
						$this->success = false;
						$this->logger("** Failed removing ".$old_file);
						//Turn on error reporting again
						error_reporting($this->error_level);
						return false;
					}
				}
			}
		} else {
			$this->success = TRUE;
			$this->logger("No old files to be removed");
		}

		// Remove upgrader removed files list
		error_reporting(0);
		$result = $this->filesystem->delete('upgrader_removed_files.txt');
		error_reporting($this->error_level);

	}

	/**
	 * Remove files recursively.
	 * 
	 * @param String dir-- the directory to delete.
	 */
	public function remove_recursively($dir) 
	{
		if ( $this->filesystem->delete($dir, TRUE) ) 
		{
			$this->success = TRUE;
			return TRUE;
		} 
		else
		{
			$this->errors[] = sprintf("Directory %s not deleted", $dir );
			$this->success = false;	
		}
	}

	/**
     * Create maintenance.php file to indicate that the website 
     * is undergoing maintenance.
     */
	public function add_maintenance_file() {
		$root = $this->filesystem->trailingslashit($this->filesystem->abspath());
		$this->logger("** Creating maintenance.php file");

		// Check if maintenance_off.php exists, then make a copy of it
		if ( $this->filesystem->exists($root.'maintenance_off.php'))
		{ 
			if ( $this->filesystem->copy( $root.'maintenance_off.php',$root.'maintenance.php',TRUE, FS_CHMOD_FILE)) 
			{
				$this->success = TRUE;
				$this->logger("** Maintenance file successfully created");
			} 
			else 
			{
				$this->success = false;
				$this->logger("** Maintenance file failed to be created");
				return false;
			}
		} 
		else 
		{
			$maintenance_message = "This website is currently undergoing maintenance. Please try again later.";
			if( $this->filesystem->put_contents($root.'maintenance.php',$maintenance_message, FS_CHMOD_FILE))
			{
				$this->success = TRUE;
				$this->logger("** Maintenance file successfully created");
			}
			else
			{
				$this->success = false;
				$this->logger("** Maintenance file failed to be created");
				return false;
			}
		}
	}

	/**
	 * Remove maintenace file
	 */
	public function remove_maintenance_file() 
	{
		$root = $this->filesystem->trailingslashit($this->filesystem->abspath());
		if ( $this->filesystem->exists($root.'maintenance.php')) 
		{ 
			if ( $this->filesystem->delete($root.'maintenance.php')) 
			{
				$this->success = TRUE;

			} else {
				$this->success = false;
				return false;
			}
		}
	}
	
	/**
	 * Adds the site_name to the application/config/config.php file
	 */
	public function add_config_details( $base_path )
	{
		$config_file = file($root.'application/config/config.php');
		$handle = fopen($root.'application/config/config.php', 'w');
	
		foreach( $config_file as $line_number => $line )
		{
			if( !empty( $base_path ) )
			{
				switch( trim(substr( $line,0,23 )) ) {
					case "\$config['site_domain']":
						fwrite($handle, str_replace("/","/".
										$base_path."/",$line ));
										break;
	
					default:
						fwrite($handle, $line);
				}
			}else {
				fwrite($handle, $line);
			}
		}
	}

	public function trailingslashit($string) {
		return $this->untrailingslashit($string) . '/';
	}

	public function untrailingslashit($string) {
		return rtrim($string, '/');
	}
}
?>