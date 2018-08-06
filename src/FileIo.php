<?php

namespace bdk;

use bdk\Str;

/**
 * File Input/Output
 */
class FileIo
{

	protected static $cfgDefault = array(
		'useLockfile' => false,	// whether to use a lockfile instead of flock()
	);
	private static $safeFopenHandles = array();

	/**
	 * Get line count
	 *
	 * @param string $filepath path to file
	 *
	 * @return boolean|int returns false on error
	 */
	public static function countLines($filepath)
	{
		$return = false;
		if (file_exists($filepath)) {
			$handle = fopen($filepath, 'r');
			if ($handle) {
				$return = 0;
				while (!feof($handle)) {
					$chunk = fgets($handle, 4096);
					$chunk_count = substr_count($chunk, "\n");
					$return = $return + $chunk_count;
				}
				fclose($handle);
			}
		}
		return $return;
	}

	/**
	 * The first row is NOT a header if
	 *    The first row has columns that are empty
	 *    The first row appears to contain dates or other common data formats (eg, xx-xx-xx)   (not implemented)
	 *
	 * Values and keys will be UTF-8 encoded
	 *
	 * @param string $filepath filepath (or filehandle)
	 * @param string $key_col  optional "primary key" to give each returned row
	 * @param array  $opts     options
	 *
	 * @return array|false
	 */
	public static function readDelimFile($filepath, $key_col = null, $opts = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $filepath, $key_col);
		$opts = array_merge(array(
			'colnames'			=> array(),
			'load_comments'		=> false,
			'empty_col_headers'	=> true,
		), $opts);
		if (is_resource($filepath)) {
			$fh = $filepath;
			rewind($fh);
		} else {
			if (!is_readable($filepath)) {
				$debug->warn('Can not read "'.$filepath.'"...');
				$debug->groupEnd();
				return false;
			}
			$fh = fopen($filepath, 'rb');
		}
		#$debug->log('colnames', $colnames);
		$fileContents = '';
		while (!feof($fh)) {
			$fileContents .= fread($fh, 8192);
		}
		$opts['isUtf8'] = Str::isUtf8($fileContents);
		fseek($fh, 0);
		// $line = self::readDelimFileFirstLine($fh);
		$opts['delim'] = self::readDelimFileDetDelim($fh);
		$colnames = self::readDelimFileDetColnames($fh, $opts);
		$lines = array();
		while (($data = fgetcsv($fh, 2048, $opts['delim'])) !== false) {
			if (count($data)==1 && $data[0]==='') {
				// blank line
				continue;
			} elseif (!$opts['load_comments'] && substr($data[0], 0, 1) === '#') {
				continue;
			} elseif ($data == $colnames) {
				continue;
			}
			$line = array();
			foreach ($colnames as $j => $colname) {
				if (is_null($colname) || $colname === '') {
					continue;
				}
				$line[$colname] = isset($data[$j])
					? trim($data[$j])
					: '';
				unset($data[$j]);
			}
			// now the numeric cols
			foreach ($data as $k => $v) {
				// nameless (numeric) columns may precede named columns
				$line[$k] = trim($v);
			}
			if (!$opts['isUtf8']) {
				$line = array_map(array('\bdk\Str', 'toUtf8'), $line);
			}
			if (!empty($key_col) && isset($line[$key_col])) {
				$key_value = $line[$key_col];
				$lines[$key_value] = $line;
			} else {
				$lines[] = $line;
			}
		}
		fclose($fh);
		$debug->groupEnd();
		return $lines;
	}

	/**
	 * [readDelimFileDetDelim description]
	 *
	 * @param resource $fh file handle
	 *
	 * @return string
	 */
	protected static function readDelimFileDetDelim($fh)
	{
		$line = '';
		if ($fh) {
			while (!feof($fh)) {
				$line = trim(fgets($fh, 4096));
				if (!empty($line)) {
					break;
				}
			}
			fseek($fh, 0);		// go back to beginning of file
		}

		$delims = array(',', "\t");
		$delim_counts = array();
		foreach ($delims as $delim) {
			$delim_counts[$delim] = substr_count($line, $delim);
		}
		arsort($delim_counts);
		$keys = array_keys($delim_counts);
		$delim = reset($keys);
		return $delim;
	}

	/**
	 * [readDelimFileDetColnames description]
	 *
	 * @param resource $fh   file handle
	 * @param array    $opts options
	 *
	 * @return array
	 */
	protected static function readDelimFileDetColnames($fh, $opts)
	{
		$debug = \bdk\Debug::getInstance();
		$colnames = $opts['colnames'];
		if (empty($colnames) && $colnames = fgetcsv($fh, 4096, $opts['delim'])) {
			$debug->log('determine colnames');
			#$debug->log('colnames', $colnames);
			$isHeaderRow	= true;
			$numericCount	= 0;
			$colnameCounts	= array();		// for indexing colnames of same name
			foreach ($colnames as $k => $v) {
				$v = trim(strtolower($v));
				if ($v === '') {
					if (!$opts['empty_col_headers']) {
						$isHeaderRow = false;
						break;
					} else {
						unset($colnames[$k]);
					}
				} else {
					if (!isset($colnameCounts[$v])) {
						$colnameCounts[$v] = 1;
					} else {
						$colnameCounts[$v]++;
						$v .= ' '.$colnameCounts[$v];
					}
					if (is_numeric($v)) {
						$numericCount++;
					}
					$colnames[$k] = !$opts['isUtf8']
						? Str::toUtf8($v)
						: $v;
				}
			}
			$debug->log('numericCount', $numericCount);
			if (!empty($colnames) && $numericCount/count($colnames) > 0) {
				$debug->log('colnames more than 0% numeric');
				$isHeaderRow = false;
			}
			if (!$isHeaderRow) {
				$debug->log('no header row');
				fseek($fh, 0);		// go back to beginning of file
				$colnames = range(0, count($colnames)-1);
			}
		}
		$debug->log('colnames', $colnames);
		return $colnames;
	}

	/**
	 * used by writeDelimFile, safeFileWrite, & safeFileAppend
	 * be sure to use safeFclose().. especially if locking... definitely when using a lockfile
	 *
	 * @param string $filepath filepath
	 * @param array  $opts     options
	 *
	 * @return resource file handle
	 * @see    safeFclose()
	 */
	public static function safeFopen($filepath, $opts = array())
	{
		$debug = \bdk\Debug::getInstance();
		// $debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $filepath);
		$return	= false;
		$opts = array_merge(array(
			'mode'		=> 'wb',
			'est_size'	=> 0,
			'lock'		=> true,
			'keep_open'	=> false,	// only applicable when lock is false
			'lockfile'	=> self::$cfgDefault['useLockfile'],
			'permissions' => 0660, // permissions for newly created files rw-rw----
		), $opts);
		if ($opts['lock']) {
			$opts['keep_open'] = false;
			$debug->time('lock obtained');
		}
		$fileHandles = false;
		if (!empty(self::$safeFopenHandles)) {
			$fileHandles = ArrayUtil::searchFields(
				self::$safeFopenHandles,
				array('filepath'=>$filepath, 'mode'=>$opts['mode'])
			);
		}
		if ($fileHandles) {
			$debug->log('using existing open handle');
			if (count($fileHandles) > 1) {
				$debug->warn('multiple open handles.. returning first');
			}
			$fh_keys = array_keys($fileHandles);
			$fh_key = array_shift($fh_keys);
			$return = $fileHandles[$fh_key]['handle'];
			self::$safeFopenHandles[$fh_key]['keep_open'] = $opts['keep_open'];
		} else {
			$fopen_mode = $opts['lock'] && preg_match('/^[wx]/', $opts['mode'])
				? substr_replace($opts['mode'], 'c', 0, 1)	// use c rather than w or x (which will undesireably truncate before getting lock)
				: $opts['mode'];
			if (version_compare(PHP_VERSION, '4.3.2', '<') && $opts['lockfile']) {
				$debug->log('not using lockfile..  required fopen() mode x not supported');
				// mode x = only create file if it does not exist
				$opts['lockfile'] = false;
			}
			if (version_compare(PHP_VERSION, '5.2.6', '<') && preg_match('/^c/', $fopen_mode)) {
				// $debug->log('mode "c" is not supported... opening with "a"');
				$fopen_mode = substr_replace($opts['mode'], 'a', 0, 1);
			}
			// $debug->log('mode passed', $opts['mode']);
			// $debug->log('mode using', $fopen_mode);
			$is_url = preg_match('#^[a-z]+://#i', $filepath);
			$dir = dirname($filepath);
		}
		$fileExists = file_exists($filepath);
		if ($return) {
			// already have filehandle
		} elseif (!$is_url && !file_exists($dir)) {
			$debug->warn('Directory does not exist: '.$dir);
			if (mkdir($dir, 0755, true)) {
				$debug->log('directory created');
				$return = self::safeFopen($filepath, $opts);
			} else {
				trigger_error('Unable to create directory: '.$dir, E_USER_ERROR);
			}
		} elseif (!$is_url && disk_free_space($dir) < $opts['est_size']+100000) {
			trigger_error('No space to write '.$filepath, E_USER_ERROR);
		} elseif (file_exists($filepath) && !is_writeable($filepath)) {
			trigger_error('The file "'.$filepath.'" is not writable');
		} elseif (false === $fh = @fopen($filepath, $fopen_mode)) {
			$debug->warn('couldn\'t open "'.$filepath.'"');
		} elseif (!$is_url && $opts['lock'] && !$opts['lockfile'] && !flock($fh, LOCK_EX)) {
			trigger_error('couldn\'t obtain lock');	// time limit will be reached first
		} else {
			if ($opts['lock']) {
				if ($opts['lockfile']) {
					while (false == $lfh = @fopen($filepath.'.lock', 'x')) {
						usleep(50000);	// .05 of second
					}
					fclose($lfh);
					$debug->log('lock file created');
				} else {
					$debug->log('exclusive lock obtained');
				}
				$debug->timeEnd('lock obtained');
			}
			if (!$fileExists) {
				// file was just created
				chmod($filepath, $opts['permissions']);
			}
			if (preg_match('/^[wx]/', $opts['mode'])) {
				$debug->log('truncating');
				ftruncate($fh, 0);
			}
			$return = $fh;
			unset($opts['est_size']);
			$opts['filepath']	= $filepath;
			$opts['handle']		= $fh;
			$fh_key = print_r($fh, true).': '.get_resource_type($fh);
			ArrayUtil::path(self::$safeFopenHandles, array($fh_key), $opts);
		}
		if ($opts['lock'] && !$return) {
			$debug->timeEnd('lock obtained', true);
		}
		// $debug->groupEnd();
		return $return;
	}

	/**
	 * Releases any lock set by safeFopen() then closes the file
	 *
	 * @param resource $fh filehandle
	 *
	 * @return boolean
	 */
	public static function safeFclose($fh)
	{
		$debug = \bdk\Debug::getInstance();
		// $debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $fh);
		$close = true;
		$fh_key = print_r($fh, true).': '.get_resource_type($fh);
		$fh_info = isset(self::$safeFopenHandles[$fh_key])
			? self::$safeFopenHandles[$fh_key]
			: null;
		if ($fh_info) {
			// $debug->log('fh_info', $fh_info);
			if ($fh_info['keep_open']) {
				$debug->log('keeping open');
				$close = false;
			} elseif ($fh_info['lock']) {
				if ($fh_info['lockfile']) {
					$debug->log('unlinking lockfile');
					unlink($fh_info['filepath'].'.lock');
				} else {
					$debug->log('releasing lock');
					flock($fh, LOCK_UN);
				}
			}
			if ($close) {
				unset(self::$safeFopenHandles[$fh_key]);
			}
		}
		$return = $close
			? fclose($fh)
			: true;
		// $debug->groupEnd();
		return $return;
	}

	/**
	 * Write a string to a file.. taking care that enough diskspace, etc exists
	 *
	 * @param string $filepath filepath
	 * @param string $str      contents to write
	 * @param array  $opts     options used by safeFopen()
	 *
	 * @return boolean
	 */
	public static function safeFileWrite($filepath, $str = '', $opts = array())
	{
		$return = false;
		$opts = array_merge(array(
			'est_size' => strlen($str),
		), $opts);
		$fh = self::safeFopen($filepath, $opts);
		if ($fh) {
			if (fwrite($fh, $str) !== false) {
				$return = true;
			} else {
				trigger_error('Error writing to '.$filepath, E_USER_ERROR);
			}
			self::safeFclose($fh);
		}
		return $return;
	}

	/**
	 * Append string to file
	 *
	 * @param string $filepath filepath
	 * @param string $str      string to append
	 * @param array  $opts     options passed to safeFopen()
	 *
	 * @return boolean
	 */
	public static function safeFileAppend($filepath, $str, $opts = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $filepath);
		$return = false;
		if (substr($str, -1, 1) != "\n") {
			$str .= "\n";
		}
		$opts = array_merge(array(
			'mode'		=> 'a',
			'est_size'	=> strlen($str),
			'lock'		=> false,
			'keep_open'	=> false,	// only applicable when not locking
		), $opts);
		$fh = self::safeFopen($filepath, $opts);
		if ($fh) {
			if (fwrite($fh, $str)) {
				$return = true;
			} else {
				trigger_error('Error writing to '.$filepath, E_USER_ERROR);
			}
			self::safeFclose($fh);
		}
		$debug->groupEnd();
		return $return;
	}

	/**
	 * Write a delimited file
	 *
	 * @param string $filepath filepath
	 * @param array  $rows     rows to write
	 * @param array  $opts     options
	 *
	 * @return boolean
	 */
	public static function writeDelimFile($filepath = null, $rows = array(), $opts = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $filepath);
		$return	= false;
		if (!is_array($rows)) {
			$rows = array();
		}
		// determine est size
		$est_size = 0;
		$sample_size = ceil(count($rows)/10);
		if ($sample_size > 1) {
			if ($sample_size > 100) {
				$sample_size = 100;
			}
			$rand_keys = array_rand($rows, $sample_size);
			foreach ($rand_keys as $k) {
				$est_size += strlen(implode(',', $rows[$k]));
			}
			$est_size = $est_size * count($rows)/$sample_size;
		}
		$opts = array_merge(array(
			'est_size'	=> $est_size,
			'char'		=> ',',
			'header'	=> true,
		), $opts);
		$fh = self::safeFopen($filepath, $opts);
		if ($fh) {
			$return = true;
			$keys = ArrayUtil::columnKeys($rows);
			if ($opts['header']) {
				$string = implode($opts['char'], $keys)."\n";
				$return = fwrite($fh, $string) !== false;
			}
			foreach ($rows as $row) {
				$values = array();
				foreach ($keys as $key) {
					$values[] = isset($row[$key])
						? $row[$key]
						: '';
				}
				$string = ArrayUtil::implodeDelim($values, $opts['char'])."\n";
				$return = fwrite($fh, $string) !== false;
				if (!$return) {
					break;
				}
			}
			if (!$return) {
				trigger_error('Error writing to '.$filepath, E_USER_ERROR);
			}
			self::safeFclose($fh);
		}
		$debug->groupEnd();
		return $return;
	}
}
