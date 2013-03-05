<?php
	define('FS_CHMOD_DIR', 0755);
	define('FS_CHMOD_FILE', 0644);
	
	/**
	 * The Base Ushahidi Filesystem. 
	 *
	 * Highly inspired by Wordpress filesystem abstraction classes
	 */
	class Ushahidi_FileSystem_Base {
		/**
		 * Whether to display debug info for connection.
		 */
		public $verbose = false;

		public $cache = array();

		public $method = '';

		/**
		 * Returns the path on the remote file system of 
		 * ABSP
		 */
		public function abspath() {
			$folder = $this->find_folder(DOCROOT); {
				if( ! $folder AND $this->is_dir('/application'))
					$folder = '/';
				return $folder;
			}
		}

		public function media_dir() {
			return $this->find_folder(MEDIA_DIR);
		}


	/**
	 * Locates a folder on the remote filesystem.
	 *
	 * Assumes that on Windows systems, Stripping off the Drive letter is OK
	 * Sanitizes \\ to / in windows filepaths.
	 *
	 *
	 * @param string $folder the folder to locate
	 * @return string The location of the remote path.
	 */
	public function find_folder($folder) {

		if ( strpos($this->method, 'ftp') !== false ) {
			$constant_overrides = array( 'FTP_BASE' => DOCROOT);
			foreach ( $constant_overrides as $constant => $dir )
				if ( defined($constant) && $folder === $dir )
					return $this->trailingslashit(constant($constant));
		} elseif ( 'direct' == $this->method ) {
			$folder = str_replace('\\', '/', $folder); //Windows path sanitisation
			return $this->trailingslashit($folder);
		}

		$folder = preg_replace('|^([a-z]{1}):|i', '', $folder); //Strip out windows drive letter if it's there.
		$folder = str_replace('\\', '/', $folder); //Windows path sanitisation

		if ( isset($this->cache[ $folder ] ) )
			return $this->cache[ $folder ];

		if ( $this->exists($folder) ) { //Folder exists at that absolute path.
			$folder = $this->trailingslashit($folder);
			$this->cache[ $folder ] = $folder;
			return $folder;
		}
		if ( $return = $this->search_for_folder($folder) )
			$this->cache[ $folder ] = $return;
		return $return;
	}

	/**
	 * Locates a folder on the remote filesystem.
	 *
	 * Expects Windows sanitized path
	 *
	 * @param string $folder the folder to locate
	 * @param string $base the folder to start searching from
	 * @param bool $loop if the function has recursed, Internal use only
	 * @return string The location of the remote path.
	 */
	private function search_for_folder($folder, $base = '.', $loop = false ) {
		if ( empty( $base ) || '.' == $base )
			$base = $this->trailingslashit($this->cwd());

		$folder = $this->untrailingslashit($folder);

		$folder_parts = explode('/', $folder);
		$last_index = array_pop( array_keys( $folder_parts ) );
		$last_path = $folder_parts[ $last_index ];

		$files = $this->dirlist( $base );

		foreach ( $folder_parts as $index => $key ) {
			if ( $index == $last_index )
				continue; //We want this to be caught by the next code block.

			// If its found, change into it and follow through looking for it.
			// If it cant find WordPress down that route, it'll continue onto the next folder level, and see if that matches, and so on.
			// If it reaches the end, and still cant find it, it'll return false for the entire function.
			if ( isset($files[ $key ]) ){
				//Lets try that folder:
				$newdir = $this->trailingslashit($this->path_join($base, $key));
				if ( $this->verbose )
					printf('Changing to %s' . '<br/>', $newdir );
				// only search for the remaining path tokens in the directory, not the full path again
				$newfolder = implode( '/', array_slice( $folder_parts, $index + 1 ) );
				if ( $ret = $this->search_for_folder( $newfolder, $newdir, $loop) )
					return $ret;
			}
		}

		//Only check this as a last resort, to prevent locating the incorrect install. All above procedures will fail quickly if this is the right branch to take.
		if (isset( $files[ $last_path ] ) ) {
			if ( $this->verbose )
				printf( 'Found %s'. '<br/>',  $base . $last_path );
			return $this->trailingslashit($base . $last_path);
		}
		if ( $loop )
			return false; //Prevent this function from looping again.
		//As an extra last resort, Change back to / if the folder wasn't found. This comes into effect when the CWD is /home/user/ but WP is at /var/www/.... mainly dedicated setups.
		return $this->search_for_folder($folder, '/', TRUE);

	}

	/**
	 * Returns the *nix style file permissions for a file
	 *
	 * From the PHP documentation page for fileperms()
	 *
	 * @link http://docs.php.net/fileperms
	 * @since 2.5
	 * @access public
	 *
	 * @param string $file string filename
	 * @return int octal representation of permissions
	 */
	function gethchmod($file){
		$perms = $this->getchmod($file);
		if (($perms & 0xC000) == 0xC000) // Socket
			$info = 's';
		elseif (($perms & 0xA000) == 0xA000) // Symbolic Link
			$info = 'l';
		elseif (($perms & 0x8000) == 0x8000) // Regular
			$info = '-';
		elseif (($perms & 0x6000) == 0x6000) // Block special
			$info = 'b';
		elseif (($perms & 0x4000) == 0x4000) // Directory
			$info = 'd';
		elseif (($perms & 0x2000) == 0x2000) // Character special
			$info = 'c';
		elseif (($perms & 0x1000) == 0x1000) // FIFO pipe
			$info = 'p';
		else // Unknown
			$info = 'u';

		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
					(($perms & 0x0800) ? 's' : 'x' ) :
					(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
					(($perms & 0x0400) ? 's' : 'x' ) :
					(($perms & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
					(($perms & 0x0200) ? 't' : 'x' ) :
					(($perms & 0x0200) ? 'T' : '-'));
		return $info;
	}

	/**
	 * Converts *nix style file permissions to a octal number.
	 *
	 * Converts '-rw-r--r--' to 0644
	 * From "info at rvgate dot nl"'s comment on the PHP documentation for chmod()
 	 *
	 * @link http://docs.php.net/manual/en/function.chmod.php#49614
	 *
	 * @param string $mode string *nix style file permission
	 * @return int octal representation
	 */
	public function getnumchmodfromh($mode) {
		$realmode = '';
		$legal =  array('', 'w', 'r', 'x', '-');
		$attarray = preg_split('//', $mode);

		for ($i=0; $i < count($attarray); $i++)
		   if ($key = array_search($attarray[$i], $legal))
			   $realmode .= $legal[$key];

		$mode = str_pad($realmode, 10, '-', STR_PAD_LEFT);
		$trans = array('-'=>'0', 'r'=>'4', 'w'=>'2', 'x'=>'1');
		$mode = strtr($mode,$trans);

		$newmode = $mode[0];
		$newmode .= $mode[1] + $mode[2] + $mode[3];
		$newmode .= $mode[4] + $mode[5] + $mode[6];
		$newmode .= $mode[7] + $mode[8] + $mode[9];
		return $newmode;
	}

	public function is_binary( $text ) {
		return (bool) preg_match('|[^\x20-\x7E]|', $text); //chr(32)..chr(127)
	}

	public function trailingslashit($string) {
		return $this->untrailingslashit($string) . '/';
	}

	public function untrailingslashit($string) {
		return rtrim($string, '/');
	}

	public function tempnam($filename = '', $dir = '') {
		if ( empty($dir) )
			$dir = $this->get_temp_dir();
		$filename = basename($filename);
		if ( empty($filename) )
			$filename = time();

		$filename = preg_replace('|\..*$|', '.tmp', $filename);

		//TODO: generate a random filename
		$filename = $dir . 'tmp'. $filename;
		touch($filename);
		return $filename;
	}

	/**
 	 * Determines a writable directory for temporary files.
 	 * Function's preference is the return value of <code>sys_get_temp_dir()</code>,
 	 * followed by your PHP temporary upload directory, followed by WP_CONTENT_DIR,
 	 * before finally defaulting to /tmp/
	 *
 	 * In the event that this function does not find a writable location,
 	 * It may be overridden by the <code>WP_TEMP_DIR</code> constant in
 	 * your <code>wp-config.php</code> file.
	 *
 	 * @since 2.5.0
 	 *
 	 * @return string Writable temporary directory
 	 */
	function get_temp_dir() {
		static $temp;

		if ( $temp )
			return $this->trailingslashit( rtrim( $temp, '\\' ) );

		$is_win = ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) );

		if ( function_exists('sys_get_temp_dir') ) {
			$temp = sys_get_temp_dir();
			if ( @is_dir( $temp ) && ( $is_win ? win_is_writable( $temp ) : @is_writable( $temp ) ) ) {
				return $this->trailingslashit( rtrim( $temp, '\\' ) );
			}
		}

		$temp = ini_get('upload_tmp_dir');
		if ( is_dir( $temp ) && ( $is_win ? win_is_writable( $temp ) : @is_writable( $temp ) ) )
			return $this->trailingslashit( rtrim( $temp, '\\' ) );

		$temp = '/tmp/';
		return $temp;
	}

	/**
	 * Test if a give filesystem path is absolute ('/foo/bar', 'c:\windows').
	 *
	 *
	 * @param string $path File path
	 * @return bool TRUE if path is absolute, false is not absolute.
	 */
	public function path_is_absolute( $path ) {
		// this is definitive if TRUE but fails if $path does not exist or contains a symbolic link
		if ( realpath($path) == $path )
			return TRUE;

		if ( strlen($path) == 0 || $path[0] == '.' )
			return false;

		// windows allows absolute paths like this
		if ( preg_match('#^[a-zA-Z]:\\\\#', $path) )
			return TRUE;

		// a path starting with / or \ is absolute; anything else is relative
		return ( $path[0] == '/' || $path[0] == '\\' );
	}

	/**
	 * Join two filesystem paths together (e.g. 'give me $path relative to $base').
	 *
	 * If the $path is absolute, then it the full path is returned.
	 *
	 * @param string $base
	 * @param string $path
	 * @return string The path with the base or absolute path.
	 */
	public function path_join( $base, $path ) {
		if ( $this->path_is_absolute($path) )
			return $path;

		return rtrim($base, '/') . '/' . ltrim($path, '/');
	}

}
?>