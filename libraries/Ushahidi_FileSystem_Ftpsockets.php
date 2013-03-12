<?php 
	/**
	 * Filesystem Class for implementing FTP Sockets.
	 */
	class Ushahidi_FileSystem_Ftpsockets extends Ushahidi_FileSystem_Base {
		public $ftp = false;
		public $errors = array();
		public $options = array();

		public function __construct($opt = '') {
			$this->method = 'ftpsockets';

			$ftp_init = new PemFtps();
			$this->ftp = new ftp();

			//Set defaults:
			$port = $opt->get('port');
			
			$hostname = $opt->get('hostname');
			
			$username = $opt->get('username');
			
			$password = $opt->get('password');

			if ( empty($port))
			{ 
				$this->options['port'] = 21;
			}
			else
			{ 
				$this->options['port'] = $port;
			}

			if ( empty($hostname) )
			{ 
				$this->errors[] = 'FTP hostname is required';
			}
			else
			{ 
				$this->options['hostname'] = $hostname;
			}

			// Check if the options provided are OK.
			if ( empty($username) )
			{ 
				$this->errors[] = 'FTP username is required';
			}
			else
			{ 
				$this->options['username'] = $username;
			}

			if ( empty($password) )
			{ 
				$this->errors[] = 'FTP password is required';
			}
			else
			{ 
				$this->options['password'] = $password;
			}
		}

		public function connect() {
			if ( ! $this->ftp )
				return FALSE;
			
			$this->ftp->setTimeout(FS_CONNECT_TIMEOUT);

			if ( ! $this->ftp->SetServer($this->options['hostname'], $this->options['port']) ) 
			{
				$this->errors[] = sprintf('Failed to connect to FTP Server %1$s:%2$s', $this->options['hostname'], $this->options['port']);
				return FALSE;
			}

			if ( ! $this->ftp->connect() ) 
			{
				$this->errors[] = sprintf('Failed to connect to FTP Server %1$s:%2$s', $this->options['hostname'], $this->options['port']);
				return FALSE;
			}

			if ( ! $this->ftp->login($this->options['username'], $this->options['password']) ) 
			{
				$this->errors[] =  sprintf('Username/Password incorrect for %s', $this->options['username']);
				return FALSE;
			}

			$this->ftp->SetType(FTP_AUTOASCII);
			$this->ftp->Passive(TRUE);
			$this->ftp->setTimeout(FS_TIMEOUT);
			return TRUE;
		}

		public function get_contents($file, $type = '', $resumepos = 0) {
			if ( ! $this->exists($file) )
				return FALSE;

			if ( empty($type) )
				$type = FTP_AUTOASCII;
			$this->ftp->SetType($type);

			$temp = wp_tempnam( $file );

			if ( ! $temphandle = fopen($temp, 'w+') )
				return FALSE;

			if ( ! $this->ftp->fget($temphandle, $file) ) {
				fclose($temphandle);
				unlink($temp);
				return ''; //Blank document, File does exist, Its just blank.
			}

			fseek($temphandle, 0); //Skip back to the start of the file being written to
			$contents = '';

			while ( ! feof($temphandle) )
				$contents .= fread($temphandle, 8192);

			fclose($temphandle);
			unlink($temp);
			return $contents;
		}

		public function get_contents_array($file) {
			return explode("\n", $this->get_contents($file) );
		}

		public function put_contents($file, $contents, $mode = false ) {
			$temp = wp_tempnam( $file );
			if ( ! $temphandle = @fopen($temp, 'w+') ) {
				unlink($temp);
				return false;
			}

			fwrite($temphandle, $contents);
			fseek($temphandle, 0); //Skip back to the start of the file being written to

			$type = $this->is_binary($contents) ? FTP_BINARY : FTP_ASCII;
			$this->ftp->SetType($type);

			$ret = $this->ftp->fput($file, $temphandle);

			fclose($temphandle);
			unlink($temp);

			$this->chmod($file, $mode);

			return $ret;
		}

		public function cwd() {
			$cwd = $this->ftp->pwd();
			if ( $cwd )
				$cwd = $this->trailingslashit($cwd);
			return $cwd;
		}

		public function chdir($file) {
			return $this->ftp->chdir($file);
		}

		public function chgrp($file, $group, $recursive = false ) {
			return false;
		}

		public function chmod($file, $mode = false, $recursive = false ) {
			if ( ! $mode ) {
				if ( $this->is_file($file) )
					$mode = FS_CHMOD_FILE;
				elseif ( $this->is_dir($file) )
					$mode = FS_CHMOD_DIR;
				else
					return false;
			}

			// chmod any sub-objects if recursive.
			if ( $recursive && $this->is_dir($file) ) {
				$filelist = $this->dirlist($file);
				foreach ( (array)$filelist as $filename => $filemeta )
					$this->chmod($file . '/' . $filename, $mode, $recursive);
			}

			// chmod the file or directory
			return $this->ftp->chmod($file, $mode);
		}

		public function chown($file, $owner, $recursive = false ) {
			return false;
		}

		public function owner($file) {
			$dir = $this->dirlist($file);
			return $dir[$file]['owner'];
		}

		public function getchmod($file) {
			$dir = $this->dirlist($file);
			return $dir[$file]['permsn'];
		}

		public function group($file) {
			$dir = $this->dirlist($file);
			return $dir[$file]['group'];
		}

		public function copy($source, $destination, $overwrite = false, $mode = false) {
			if ( ! $overwrite && $this->exists($destination) )
				return false;

			$content = $this->get_contents($source);
			if ( false === $content )
				return false;

			return $this->put_contents($destination, $content, $mode);
		}

		public function copy_recursively($source, $dest, $overwrite = false, $mode=false) {
			
			if( $this->is_file($source))
			{
				return $this->copy($source, $dest, $overwrite, $mode);
			}

			if($this->is_dir($source))
			{

				// Make parent directory
				if ($dest[strlen($dest)-1]=='/') 
					$__dest = $this->trailingslashit($dest.basename($source));
				else 
					$__dest = $dest;

				$this->mkdir($__dest);
				$filelist = $this->dirlist($this->trailingslashit($source));

				if ( !empty($filelist)) {
					foreach ($filelist as $copy_file) {
						
						if ( $this->is_dir( $this->trailingslashit( $source ).$copy_file['name'] ) ) 
						{
							$this->copy_recursively($this->trailingslashit($source).$copy_file['name'],$this->trailingslashit($__dest).$copy_file['name'],$overwrite,$mode);
							
						} else {	
							$this->copy($this->trailingslashit($source).$copy_file['name'],$this->trailingslashit($__dest).$copy_file['name'],$overwrite,$mode);
						}
						
					}
				}			
			}
		}

		public function move($source, $destination, $overwrite = false ) {
			return $this->ftp->rename($source, $destination);
		}

		public function delete($file, $recursive = false, $type = false) {
			if ( empty($file) )
				return false;
			if ( 'f' == $type || $this->is_file($file) )
				return $this->ftp->delete($file);
			if ( !$recursive )
				return $this->ftp->rmdir($file);

			return $this->ftp->mdel($file);
		}

		public function exists($file) {
			return $this->ftp->is_exists($file);
		}

		public function is_file($file) {
			if ( $this->is_dir($file) )
				return false;
			if ( $this->exists($file) )
				return TRUE;
			return false;
		}

		public function is_dir($path) {
			$cwd = $this->cwd();
			if ( $this->chdir($path) ) {
				$this->chdir($cwd);
				return TRUE;
			}
			return false;
		}

		public function is_readable($file) {
			//Get dir list, Check if the file is writable by the current user??
			return TRUE;
		}

		public function is_writable($file) {
			//Get dir list, Check if the file is writable by the current user??
			return TRUE;
		}

		public function atime($file) {
			return false;
		}

		public function mtime($file) {
			return $this->ftp->mdtm($file);
		}

		public function size($file) {
			return $this->ftp->filesize($file);
		}

		public function touch($file, $time = 0, $atime = 0 ) {
			return false;
		}

		public function mkdir($path, $chmod = false, $chown = false, $chgrp = false ) {
			$path = untrailingslashit($path);
			if ( empty($path) )
				return false;

			if ( ! $this->ftp->mkdir($path) )
				return false;
			if ( ! $chmod )
				$chmod = FS_CHMOD_DIR;
			$this->chmod($path, $chmod);
			if ( $chown )
				$this->chown($path, $chown);
			if ( $chgrp )
				$this->chgrp($path, $chgrp);
			return TRUE;
		}

		public function rmdir($path, $recursive = false ) {
			$this->delete($path, $recursive);
		}

		public function dirlist($path = '.', $include_hidden = TRUE, $recursive = false ) {
			if ( $this->is_file($path) ) {
				$limit_file = basename($path);
				$path = dirname($path) . '/';
			} else {
				$limit_file = false;
			}

			$list = $this->ftp->dirlist($path);
			if ( empty($list) && !$this->exists($path) )
				return FALSE;

			$ret = array();
			foreach ( $list as $struc ) {

				if ( '.' == $struc['name'] || '..' == $struc['name'] )
					continue;

				if ( ! $include_hidden && '.' == $struc['name'][0] )
					continue;

				if ( $limit_file && $struc['name'] != $limit_file )
					continue;

				if ( 'd' == $struc['type'] ) {
					if ( $recursive )
						$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $include_hidden, $recursive);
					else
						$struc['files'] = array();
				}

				// Replace symlinks formatted as "source -> target" with just the source name
				if ( $struc['islink'] )
					$struc['name'] = preg_replace( '/(\s*->\s*.*)$/', '', $struc['name'] );

				$ret[ $struc['name'] ] = $struc;
			}
			return $ret;
		}

		public function __destruct() {
			$this->ftp->quit();
		}
	}
?>