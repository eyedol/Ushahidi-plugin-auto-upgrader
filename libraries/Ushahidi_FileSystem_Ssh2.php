<?php
	require_once('Ushahidi_FileSystem_Base.php');

	/**
 	 * Filesystem Class for implementing SSH2.
 	 */
	class Ushahidi_FileSystem_Ssh2 extends Ushahidi_FileSystem_Base {
	
		public $link = false;
		public $sftp_link = false;
		public $keys = false;
		public $errors = array();
		public $options = array();

		function __construct($opt='') {
			$this->method = 'ssh2';

			//Check if possible to use ssh2 functions.
			if ( ! extension_loaded('ssh2') ) {
				$this->errors[] = 'The ssh2 PHP extension is not available';
				return false;
			}
			if ( !function_exists('stream_get_contents') ) {
				$this->errors[] = 'The ssh2 PHP extension is available, however, we require the PHP5 function <code>stream_get_contents()</code>';
				return false;
			}

			// Set defaults:
			if ( empty($opt['port']) )
				$this->options['port'] = 22;
			else
				$this->options['port'] = $opt['port'];

			if ( empty($opt['hostname']) )
				$this->errors[] = 'SSH2 hostname is required';
			else
				$this->options['hostname'] = $opt['hostname'];

			if ( ! empty($opt['base']) )
				$this->wp_base = $opt['base'];

			// Check if the options provided are OK.
			if ( !empty ($opt['public_key']) && !empty ($opt['private_key']) ) {
				$this->options['public_key'] = $opt['public_key'];
				$this->options['private_key'] = $opt['private_key'];

				$this->options['hostkey'] = array('hostkey' => 'ssh-rsa');

				$this->keys = TRUE;
			} elseif ( empty ($opt['username']) ) {
				$this->errors[] = 'SSH2 username is required';
			}

			if ( !empty($opt['username']) )
				$this->options['username'] = $opt['username'];

			if ( empty ($opt['password']) ) {
				if ( !$this->keys )	//password can be blank if we are using keys
					$this->errors[] = 'SSH2 password is required';
			} else {
				$this->options['password'] = $opt['password'];
			}
		}


		function connect() {
			if ( ! $this->keys ) {
				$this->link = @ssh2_connect($this->options['hostname'], $this->options['port']);
			} else {
				$this->link = @ssh2_connect($this->options['hostname'], $this->options['port'], $this->options['hostkey']);
			}

			if ( ! $this->link ) {
				$this->errors[] = sprintf('Failed to connect to SSH2 Server %1$s:%2$s', $this->options['hostname'], $this->options['port']);
				return false;
			}

			if ( !$this->keys ) {
				if ( ! @ssh2_auth_password($this->link, $this->options['username'], $this->options['password']) ) {
					$this->errors[] = sprintf('Username/Password incorrect for %s', $this->options['username']);
					return false;
				}
			} else {
				if ( ! @ssh2_auth_pubkey_file($this->link, $this->options['username'], $this->options['public_key'], $this->options['private_key'], $this->options['password'] ) ) {
					$this->errors[] = sprintf('Public and Private keys incorrect for %s', $this->options['username']);
					return false;
				}
			}

			$this->sftp_link = ssh2_sftp($this->link);

			return TRUE;
		}

		function run_command( $command, $returnbool = false) {

			if ( ! $this->link )
				return false;

			if ( ! ($stream = ssh2_exec($this->link, $command)) ) {
				$this->errors[] = sprintf('Unable to perform command: %s', $command);
			} else {
				stream_set_blocking( $stream, TRUE );
				stream_set_timeout( $stream, FS_TIMEOUT );
				$data = stream_get_contents( $stream );
				fclose( $stream );

				if ( $returnbool )
					return ( $data === false ) ? false : '' != trim($data);
				else
					return $data;
			}
			return false;
		}

		function get_contents($file, $type = '', $resumepos = 0 ) {
			$file = ltrim($file, '/');
			return file_get_contents('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function get_contents_array($file) {
			$file = ltrim($file, '/');
			return file('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function put_contents($file, $contents, $mode = false ) {
			$file = ltrim($file, '/');
			$ret = file_put_contents('ssh2.sftp://' . $this->sftp_link . '/' . $file, $contents);

			$this->chmod($file, $mode);

			return false !== $ret;
		}

		function cwd() {
			$cwd = $this->run_command('pwd');
			if ( $cwd )
				$cwd = trailingslashit($cwd);
			return $cwd;
		}

		function chdir($dir) {
			return $this->run_command('cd ' . $dir, TRUE);
		}

		function chgrp($file, $group, $recursive = false ) {
			if ( ! $this->exists($file) )
				return false;
			if ( ! $recursive || ! $this->is_dir($file) )
				return $this->run_command(sprintf('chgrp %o %s', $mode, escapeshellarg($file)), TRUE);
			return $this->run_command(sprintf('chgrp -R %o %s', $mode, escapeshellarg($file)), TRUE);
		}

		function chmod($file, $mode = false, $recursive = false) {
			if ( ! $this->exists($file) )
				return false;

			if ( ! $mode ) {
				if ( $this->is_file($file) )
					$mode = FS_CHMOD_FILE;
				elseif ( $this->is_dir($file) )
					$mode = FS_CHMOD_DIR;
				else
					return false;
			}

			if ( ! $recursive || ! $this->is_dir($file) )
				return $this->run_command(sprintf('chmod %o %s', $mode, escapeshellarg($file)), TRUE);
			return $this->run_command(sprintf('chmod -R %o %s', $mode, escapeshellarg($file)), TRUE);
		}

		function chown($file, $owner, $recursive = false ) {
			if ( ! $this->exists($file) )
				return false;
			if ( ! $recursive || ! $this->is_dir($file) )
				return $this->run_command(sprintf('chown %o %s', $mode, escapeshellarg($file)), TRUE);
			return $this->run_command(sprintf('chown -R %o %s', $mode, escapeshellarg($file)), TRUE);
		}

		function owner($file) {
			$owneruid = @fileowner('ssh2.sftp://' . $this->sftp_link . '/' . ltrim($file, '/'));
			if ( ! $owneruid )
				return false;
			if ( ! function_exists('posix_getpwuid') )
				return $owneruid;
			$ownerarray = posix_getpwuid($owneruid);
			return $ownerarray['name'];
		}

		function getchmod($file) {
			return substr(decoct(@fileperms( 'ssh2.sftp://' . $this->sftp_link . '/' . ltrim($file, '/') )),3);
		}

		function group($file) {
			$gid = @filegroup('ssh2.sftp://' . $this->sftp_link . '/' . ltrim($file, '/'));
			if ( ! $gid )
				return false;
			if ( ! function_exists('posix_getgrgid') )
				return $gid;
			$grouparray = posix_getgrgid($gid);
			return $grouparray['name'];
		}

		function copy($source, $destination, $overwrite = false, $mode = false) {
			if ( ! $overwrite && $this->exists($destination) )
				return false;
			$content = $this->get_contents($source);
			if ( false === $content)
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

		function move($source, $destination, $overwrite = false) {
			return @ssh2_sftp_rename($this->link, $source, $destination);
		}

		function delete($file, $recursive = false, $type = false) {
			if ( 'f' == $type || $this->is_file($file) )
				return ssh2_sftp_unlink($this->sftp_link, $file);
			if ( ! $recursive )
				return ssh2_sftp_rmdir($this->sftp_link, $file);
			$filelist = $this->dirlist($file);
			if ( is_array($filelist) ) {
				foreach ( $filelist as $filename => $fileinfo) {
					$this->delete($file . '/' . $filename, $recursive, $fileinfo['type']);
				}
			}
			return ssh2_sftp_rmdir($this->sftp_link, $file);
		}

		function exists($file) {
			$file = ltrim($file, '/');
			return file_exists('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function is_file($file) {
			$file = ltrim($file, '/');
			return is_file('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function is_dir($path) {
			$path = ltrim($path, '/');
			return is_dir('ssh2.sftp://' . $this->sftp_link . '/' . $path);
		}

		function is_readable($file) {
			$file = ltrim($file, '/');
			return is_readable('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function is_writable($file) {
			$file = ltrim($file, '/');
			return is_writable('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function atime($file) {
			$file = ltrim($file, '/');
			return fileatime('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function mtime($file) {
			$file = ltrim($file, '/');
			return filemtime('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function size($file) {
			$file = ltrim($file, '/');
			return filesize('ssh2.sftp://' . $this->sftp_link . '/' . $file);
		}

		function touch($file, $time = 0, $atime = 0) {
			//Not implemented.
		}

		function mkdir($path, $chmod = false, $chown = false, $chgrp = false) {
			$path = untrailingslashit($path);
			if ( empty($path) )
				return false;

			if ( ! $chmod )
				$chmod = FS_CHMOD_DIR;
			if ( ! ssh2_sftp_mkdir($this->sftp_link, $path, $chmod, TRUE) )
				return false;
			if ( $chown )
				$this->chown($path, $chown);
			if ( $chgrp )
				$this->chgrp($path, $chgrp);
			return TRUE;
		}

		function rmdir($path, $recursive = false) {
			return $this->delete($path, $recursive);
		}

		function dirlist($path, $include_hidden = TRUE, $recursive = false) {
			if ( $this->is_file($path) ) {
				$limit_file = basename($path);
				$path = dirname($path);
			} else {
				$limit_file = false;
			}

			if ( ! $this->is_dir($path) )
				return false;

			$ret = array();
			$dir = @dir('ssh2.sftp://' . $this->sftp_link .'/' . ltrim($path, '/') );

			if ( ! $dir )
				return false;

			while (false !== ($entry = $dir->read()) ) {
				$struc = array();
				$struc['name'] = $entry;

				if ( '.' == $struc['name'] || '..' == $struc['name'] )
					continue; //Do not care about these folders.

				if ( ! $include_hidden && '.' == $struc['name'][0] )
					continue;

				if ( $limit_file && $struc['name'] != $limit_file )
					continue;

				$struc['perms'] 	= $this->gethchmod($path.'/'.$entry);
				$struc['permsn']	= $this->getnumchmodfromh($struc['perms']);
				$struc['number'] 	= false;
				$struc['owner']    	= $this->owner($path.'/'.$entry);
				$struc['group']    	= $this->group($path.'/'.$entry);
				$struc['size']    	= $this->size($path.'/'.$entry);
				$struc['lastmodunix']= $this->mtime($path.'/'.$entry);
				$struc['lastmod']   = date('M j',$struc['lastmodunix']);
				$struc['time']    	= date('h:i:s',$struc['lastmodunix']);
				$struc['type']		= $this->is_dir($path.'/'.$entry) ? 'd' : 'f';

				if ( 'd' == $struc['type'] ) {
					if ( $recursive )
						$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $include_hidden, $recursive);
					else
						$struc['files'] = array();
				}

				$ret[ $struc['name'] ] = $struc;
			}
			$dir->close();
			unset($dir);
			return $ret;
		}
	}
?>