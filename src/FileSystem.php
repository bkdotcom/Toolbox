<?php

namespace bdk;

/**
 * FileSystem
 */
class FileSystem
{

	private $debug = null;
	public $cfg = array(
		'ds'		=> DIRECTORY_SEPARATOR,
		'ds_replace'=> null,  // ds_replace is the wrong slash that needs replaced
	);
	public $foundDirs = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->debug = \bdk\Debug::getInstance();
		$this->cfg['ds_replace'] = DIRECTORY_SEPARATOR == '/'
			? '\\'
			: '/';
	}

	/**
	 * find the base/root directory that all paths share
	 *
	 * @param array $paths paths
	 *
	 * @return string $path
	 */
	public function commonDir($paths)
	{
		// $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$re_split = '#[\\\/]#';
		$count = 0;
		foreach ($paths as $k => $path) {
			if (empty($path)) {
				unset($paths[$k]);
				continue;
			}
			$ds = strpos($path, '/') !== false
				? '/'
				: $this->cfg['ds'];
			$levels = preg_split($re_split, $path);
			if (count($levels) > $count) {
				$count = count($levels);
			}
		}
		foreach ($paths as $path) {
			$parts = preg_split($re_split, $path);
			while ($count >= 0) {
				if (array_slice($levels, 0, $count) == array_slice($parts, 0, $count)) {
					$levels = array_slice($levels, 0, $count);
					continue 2;
				}
				$count--;
			}
		}
		$return = !empty($levels)
			? implode($ds, $levels)
			: '';
		// $this->debug->groupEnd();
		return $return;
	}

	/**
	 * returns -1 on not deleted
	 *		0 on error
	 *		1 on deleted
	 *
	 * @param string  $dir           path
	 * @param boolean $must_be_empty = true whether or not a dir may contain files... containing an empty dir is considered empty
	 *
	 * @return int success
	 */
	public function delTree($dir, $must_be_empty = true)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $dir);
		$cfg = $this->cfg;
		$dir = str_replace($cfg['ds_replace'], $cfg['ds'], $dir);
		if (substr($dir, -1) != $cfg['ds']) {
			$dir .= $cfg['ds'];		// add ending slash if not present
		}
		if (!file_exists($dir)) {
			$this->debug->log('does not exist');
			$return = 1;
		} elseif (is_dir($dir)) {
			$files = glob($dir.'*');
			$children = false;
			foreach ($files as $file) {
				#$this->debug->log('file', $file);
				$return = -1;
				if (is_dir($file)) {
					$return = call_user_func(array($this, __FUNCTION__), $file, $must_be_empty);
				} elseif (!$must_be_empty) {
					$return = unlink($file);
				}
				#$this->debug->log('return', $return);
				if ($return < 1) {
					$children = true;
				}
			}
			#$this->debug->log('children', $children);
			$return = -1;
			if (!$children) {
				$this->debug->log('no children...remove the dir');
				$return = rmdir($dir);
			}
		} else {
			$this->debug->log('exists, but not a dir');
			$return = 0;
		}
	    #$this->debug->log('return', $return);
	    $this->debug->groupEnd();
	    return $return;
	}

	/**
	 * a "better" stat() function
	 *
	 * @param string $fp filepath
	 *
	 * @return array
	 */
	public function fileStats($fp)
	{
		clearstatcache();
		$ss=@stat($fp);
		if (!$ss) {
			return false; // Couldnt stat file
		}
		$ts=array(
			0140000 => 'ssocket',
			0120000 => 'llink',
			0100000 => '-file',
			0060000 => 'bblock',
			0040000 => 'ddir',
			0020000 => 'cchar',
			0010000 => 'pfifo'
		);
		$p = $ss['mode'];
		$t = decoct($ss['mode'] & 0170000); // File Encoding Bit
		$str = (array_key_exists(octdec($t), $ts)) ? $ts[octdec($t)]{0} : 'u';
		$str .= (($p&0x0100)?'r':'-').(($p&0x0080)?'w':'-');
		$str .= (($p&0x0040)?(($p&0x0800)?'s':'x'):(($p&0x0800)?'S':'-'));
		$str .= (($p&0x0020)?'r':'-').(($p&0x0010)?'w':'-');
		$str .= (($p&0x0008)?(($p&0x0400)?'s':'x'):(($p&0x0400)?'S':'-'));
		$str .= (($p&0x0004)?'r':'-').(($p&0x0002)?'w':'-');
		$str .= (($p&0x0001)?(($p&0x0200)?'t':'x'):(($p&0x0200)?'T':'-'));
		$s=array(
			'perms'=>array(
				'umask'		=> sprintf("%04o", @umask()),
				'human'		=> $str,
				'octal1'	=> sprintf("%o", ($ss['mode'] & 000777)),
				'octal2'	=> sprintf("0%o", 0777 & $p),
				'decimal'	=> sprintf("%04o", $p),
				'fileperms'	=> @fileperms($file),
				'mode1'		=> $p,
				'mode2'		=> $ss['mode']
			),
			'owner'=>array(
				'fileowner'	=> $ss['uid'],
				'filegroup'	=> $ss['gid'],
				'owner'		=> (function_exists('posix_getpwuid'))?@posix_getpwuid($ss['uid']):'',
				'group'		=> (function_exists('posix_getgrgid'))?@posix_getgrgid($ss['gid']):''
				),
			'file'=>array(
				'filename'	=> $file,
				'realpath'	=> (@realpath($file) != $file) ? @realpath($file) : '',
				'dirname'	=> @dirname($file),
				'basename'	=> @basename($file)
			),
			'filetype'=>array(
				'type'			=> substr($ts[octdec($t)], 1),
				'type_octal'	=> sprintf("%07o", octdec($t)),
				'is_file'		=> @is_file($file),
				'is_dir'		=> @is_dir($file),
				'is_link'		=> @is_link($file),
				'is_readable'	=> @is_readable($file),
				'is_writable'	=> @is_writable($file)
			),
			'device'=>array(
				'device'		=> $ss['dev'],	// Device
				'device_number'	=> $ss['rdev'], // Device number, if device.
				'inode'			=> $ss['ino'],	// File serial number
				'link_count'	=> $ss['nlink'], // link count
				'link_to'		=> ($ss['type']=='link') ? @readlink($file) : ''
			),
			'size'=>array(
				'size'			=> $ss['size'], // Size of file, in bytes.
				'blocks'		=> $ss['blocks'], // Number 512-byte blocks allocated
				'block_size'	=> $ss['blksize'] // Optimal block size for I/O.
			),
			'time'=>array(
				'mtime'			=> $ss['mtime'], // Time of last modification
				'atime'			=> $ss['atime'], // Time of last access.
				'ctime'			=> $ss['ctime'], // Time of last status change
				'accessed'		=> @date('Y M D H:i:s', $ss['atime']),
				'modified'		=> @date('Y M D H:i:s', $ss['mtime']),
				'created'		=> @date('Y M D H:i:s', $ss['ctime'])
			),
		);
		clearstatcache();
		return $s;
	}

	/**
	 *	criteria = array(	// all optional
	 *		dir
	 *		regexs		regexs to match against filename (not filepath)
	 *		mod_since	timestamp
	 *		mod_before	timestamp
	 *		contains	regex string
	 *		recurse		bool (default: true)
	 *		save_dirs	bool (default: false)	// will be saved to $this->foundDirs
	 *	);
	 *
	 * @param array $criteria criteria
	 *
	 * @return array files
	 */
	public function find($criteria = array())
	{
		$cfg = $this->cfg;
		if (!isset($criteria['initialized'])) {
			$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $criteria['dir']);
			$criteria_default = array(
				'initialized'	=> true,
				'recurse'		=> true,
				'save_dirs'		=> false,
				'search_extensions' => array('csv','txt','inc','php','css','html','htm','pl','ini','js'),
			);
			$criteria = array_merge($criteria_default, $criteria);
			$criteria['dir'] = str_replace($cfg['ds_replace'], $cfg['ds'], $criteria['dir']);
			$criteria['dir'] = rtrim($criteria['dir'], $cfg['ds']);	// remove any trailing slash
			if ($criteria['save_dirs']) {
				$this->foundDirs = array();
			}
		} else {
			$this->debug->groupCollapsed();
		}
		$files_found = array();
		$dir = $criteria['dir'];
		if (is_dir($dir) && is_readable($dir)) {
			if ($dh = opendir($dir)) {
				while (($filename = readdir($dh)) !== false) {
					$fp = $dir.$cfg['ds'].$filename;
					#$this->debug->log('fp' .$fp);
					if ($filename == '.' || $filename == '..') {
					} elseif (@is_dir($fp)) {
						if ($criteria['save_dirs']) {
							$this->foundDirs[] = $fp;
						}
						if ($criteria['recurse']) {
							$criteria['dir'] = $fp;
							$more = $this->find($criteria);
							array_splice($files_found, count($files_found), 0, $more);
						}
					} else {
						$meets_criteria = $this->findCheckCriteria($fp, $criteria);
						if ($meets_criteria) {
							$files_found[] = $fp;
						}
					}
				}
				closedir($dh);
			}
		}
		$this->debug->groupEnd();
		return $files_found;
	}

	/**
	 * find check criteria
	 *
	 * @param string $filepath filepath
	 * @param array  $criteria criteria
	 *
	 * @return boolean
	 */
	protected function findCheckCriteria($filepath, $criteria)
	{
		$match = true;
		$filename = basename($filepath);
		if ($match && isset($criteria['regexs'])) {
			if (!is_array($criteria['regexs'])) {
				$criteria['regexs'] = array($criteria['regexs']);
			}
			$match = false;
			foreach ($criteria['regexs'] as $regex) {
				if (preg_match($regex, $filename)) {
					$match = true;
					break;
				}
			}
		}
		if ($match && isset($criteria['mod_since'])) {
			$match = $criteria['mod_since'] < @filemtime($filepath);
		}
		if ($match && isset($criteria['mod_before'])) {
			$match = $criteria['mod_before'] > @filemtime($filepath);
		}
		if ($match && isset($criteria['contains'])) {
			$match = false;
			if (preg_match('/\.(.*?)$/', $filename, $matches)) {
				$extension = strtolower($matches[1]);
				if (in_array($extension, $criteria['search_extensions'])) {
					if ($contents = file_get_contents($filepath)) {
						$match = preg_match($criteria['contains'], $contents);
						unset($contents);
					}
				}
			}
		}
		return $match;
	}

	/**
	 * get user info
	 *
	 * @param string|integer $uid userID
	 *
	 * @return array
	 */
	public function getUserinfo($uid = null)
	{
		$ret = array();
		if (extension_loaded('posix')) {
			if (is_null($uid)) {
				$uid = posix_geteuid();	// get effective userid
			} elseif (is_string($uid)) {
				// username passed?
				$ret = posix_getpwnam($uid);
			}
			if (empty($ret)) {
				$ret = posix_getpwuid($uid);
			}
			$ret['group'] = posix_getgrgid($uid_info['gid']);
			unset($ret['gid']);	// is inside group array
		}
		return $ret;
	}

	/**
	 * get relative path
	 *
	 * @param string $fp_a filepath a
	 * @param string $fp_b filepath b
	 *
	 * @return string
	 */
	public function relPath($fp_a, $fp_b)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		#$this->debug->log('fp_a',$fp_a);
		#$this->debug->log('fp_b',$fp_b);
		$cfg = $this->cfg;
		$return = $fp_b;
		$commonDir = $this->commonDir(array($fp_a,$fp_b));
		#$this->debug->log('common_dir',$common_dir);
		if ($commonDir) {
			$a_remain = strlen($commonDir) < strlen($fp_a)
				? substr($fp_a, strlen($commonDir))
				: '';
			$b_remain = strlen($commonDir) < strlen($fp_b)
				? substr($fp_b, strlen($commonDir))
				: '';
			#$this->debug->log('a_remain',$a_remain);
			#$this->debug->log('b_remain',$b_remain);
			$down_path = '.';
			if ($a_remain) {
				$a_is_file		= preg_match('|\.\w{3,4}$|', $a_remain);
				$a_dir_count	= preg_match_all('#[\\\/]#', $a_remain)-1;
				if (!$a_is_file) {
					$a_dir_count++;
				}
				$down_path = '.'.str_repeat($cfg['ds'].'..', $a_dir_count);
			}
			#$this->debug->log('down_path',$down_path);
			$return = $down_path.$b_remain;	// replace backslash with forward-slash
		}
		$return = str_replace($cfg['ds_other'], $cfg['ds'], $return);
		$this->debug->log('return', $return);
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * replaces escape chars and reserved chars with "_"
	 *
	 * @param string $str filename/string to make safe
	 *
	 * @return string filename
	 */
	public static function safeFilename($str)
	{
		// $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$reserved = preg_quote('\/:*?"<>', '/');
		$str = preg_replace('/([\\x00-\\x1f'.$reserved.'])/', '_', $str);
		// $this->debug->groupEnd();
		return $str;
	}
}
