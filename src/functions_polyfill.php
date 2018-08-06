<?php

/**
 * Shim functions
 */

/*
string  file_get_contents(string $file)								// 4.3.0
string	str_split(string,length)									// 5
string	htmlspecialchars_decode($string='',$flags=ENT_COMPAT)		// 5.1.0
string	hex2bin($data)												// 5.4.0
string	json_decode($data)											// 5.2.0
string	json_encode($data)											// 5.2.0
int		memory_get_peak_usage()										// 5.2.0
bool	getmxrr(string $hostname, array &$mxhosts)					// 5.3 (windows)
*/

if (!defined('E_STRICT')) {
	define('E_STRICT', 2048);				// 5.0.0
}
if (!defined('E_RECOVERABLE_ERROR')) {
	define('E_RECOVERABLE_ERROR', 4096);	// 5.2.0
}
if (!defined('E_DEPRECATED')) {
	define('E_DEPRECATED', 8192);			// 5.3.0
}
if (!defined('E_USER_DEPRECATED')) {
	define('E_USER_DEPRECATED', 16384);		// 5.3.0
}
if (!defined('PHP_EOL')) {
	if (stristr(PHP_OS, 'WIN')) {
		define('PHP_EOL', "\r\n");
	} elseif (stristr(PHP_OS, 'DAR')) {
		define('PHP_EOL', "\r");
	} else {
		define('PHP_EOL', "\n");
	}
}

if (!function_exists('file_get_contents')) {
	/**
	 * PHP 4.3.0
	 * @param string $filepath filepath
	 *
	 * @return string
	 */
	function file_get_contents($filepath)
	{
		$return = false;
		if (is_readable($filepath) && ( $fh = fopen($filepath, 'rb') )) {
			//avoid fread($fh, filesize($filepath))... filesize could be cached
			$return = '';
			while (!feof($fh)) {
				$return .= fread($fh, 8192);
			}
			fclose($fh);
		}
		return $return;
	}
}

if (!function_exists('str_split')) {
	/**
	 * PHP 5
	 * @param string $str string
	 * @param int    $len length
	 *
	 * @return array
	 */
	function str_split($str, $len = 1)
	{
		//Return an array with 1 less item then the one we have
		return array_slice(split("-l-", chunk_split($str, $len, '-l-')), 0, -1);
	}
}

if (!function_exists('htmlspecialchars_decode')) {
	/**
	 * PHP 5.1.0
	 * @param string $string string
	 * @param int    $flags  = ENT_COMPAT | ENT_HTML401
	 *
	 * @return string
	 */
	function htmlspecialchars_decode($string = '', $flags = null)
	{
		if (empty($GLOBALS['htmlspecial_chars'])) {
			if ($flags === null) {
				$flags = ENT_QUOTES | ENT_HTML401;
			}
			$tt = get_html_translation_table(HTML_SPECIALCHARS, $flags);	// HTML_SPECIALCHARS,ENT_QUOTES
			if ($flags & ENT_QUOTES == ENT_QUOTES) {	// bitwise AND
				$tt['&#039;'] = '\'';
			}
			$GLOBALS['htmlspecial_chars'] = array_keys($tt);
			$GLOBALS['htmlspecial_ents'] = array_values($tt);
		}
		$string = str_replace($GLOBALS['htmlspecial_ents'], $GLOBALS['htmlspecial_chars'], $string);
		return $string;
	}
}

if (!function_exists('hex2bin')) {
	/**
	 * PHP 5.4.0
	 *
	 * @param string $hex hexidecimal string
	 *
	 * @return string
	 */
	function hex2bin($hex)
	{
		return pack('H*', $hex);
	}
}

if (!function_exists('json_decode')) {
	/**
	 * PHP 5.2.0
	 * http://simplejson-php.googlecode.com/svn/trunk/simplejson.php
	 * @param string $json  JSON string
	 * @param bool   $assoc return assoc array (default = false)
	 *
	 * @return array
	 */
	function json_decode($json, $assoc = false)
	{
		/* by default we don't tolerate ' as string delimiters
			if you need this, then simply change the comments on
			the following lines: */
		// $matchString = '/(".*?(?<!\\\\)"|\'.*?(?<!\\\\)\')/';
		$matchString = '/".*?(?<!\\\\)"/';
		// safety / validity test
		$t = preg_replace($matchString, '', $json);
		$t = preg_replace('/[,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/', '', $t);
		if ($t != '') {
			return null;
		}
		// build to/from hashes for all strings in the structure
		$s2m = array();
		$m2s = array();
		preg_match_all($matchString, $json, $m);
		foreach ($m[0] as $s) {
			$hash		= '"' . md5($s) . '"';
			$s2m[$s]	= $hash;
			$m2s[$hash]	= str_replace('$', '\$', $s);  // prevent $ magic
		}
		// hide the strings
		$json = strtr($json, $s2m);
		// convert JS notation to PHP notation
		$a = ($assoc) ? '' : '(object) ';
		$json = strtr($json, array(
			':' => '=>',
			'[' => 'array(',
			'{' => "{$a}array(",
			']' => ')',
			'}' => ')'
		));
		// remove leading zeros to prevent incorrect type casting
		$json = preg_replace('~([\s\(,>])(-?)0~', '$1$2', $json);
		// return the strings
		$json = strtr($json, $m2s);
		/* "eval" string and return results.
			As there is no try statement in PHP4, the trick here
			is to suppress any parser errors while a function is
			built and then run the function if it got made. */
		$f = @create_function('', 'return '.$json.';');
		$r = ($f) ? $f() : null;
		// free mem (shouldn't really be needed, but it's polite)
		unset($s2m);
		unset($m2s);
		unset($f);
		return $r;
	}
}

if (!function_exists('json_encode')) {
	/**
	 * PHP 5.2.0
	 * @param mixed $data data to encode
	 *
	 * @return string
	 */
	function json_encode($data)
	{
		if (is_array($data) || is_object($data)) {
			$islist = is_array($data) && ( empty($data) || array_keys($data) === range(0, count($data)-1) );
			if ($islist) {
				$json = '[' . implode(',', array_map(__FUNCTION__, $data)) . ']';
			} else {
				$items = array();
				foreach ($data as $key => $value) {
					$items[] = call_user_func(__FUNCTION__, $key) . ':' . call_user_func(__FUNCTION__, $value);
				}
				$json = '{' . implode(',', $items) . '}';
			}
		} elseif (is_string($data)) {
			# Escape non-printable or Non-ASCII characters.
			# I also put the \\ character first, as suggested in comments on the 'addclashes' page.
			$string	= '"' . addcslashes($data, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"';
			$json	= '';
			$len	= strlen($string);
			# Convert UTF-8 to Hexadecimal Codepoints.
			for ($i = 0; $i < $len; $i++) {
				$char = $string[$i];
				$c1 = ord($char);
				# Single byte;
				if ($c1 < 128) {
					$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
					continue;
				}
				# Double byte
				$c2 = ord($string[++$i]);
				if (($c1 & 32) === 0) {
					$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
					continue;
				}
				# Triple
				$c3 = ord($string[++$i]);
				if (($c1 & 16) === 0) {
					$json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128));
					continue;
				}
				# Quadruple
				$c4 = ord($string[++$i]);
				if (($c1 & 8 ) === 0) {
					$u = (($c1 & 15) << 2) + (($c2>>4) & 3) - 1;
					$w1 = (54<<10) + ($u<<6) + (($c2 & 15) << 2) + (($c3>>4) & 3);
					$w2 = (55<<10) + (($c3 & 15)<<6) + ($c4-128);
					$json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
				}
			}
		} else {
			# int, floats, bools, null
			$json = strtolower(var_export($data, true));
		}
		return $json;
	}
}

if (!function_exists('memory_get_peak_usage')) {
	/**
	 * PHP 5.2.0
	 * Dummy function... just returns 0
	 *
	 * @return int 0
	 */
	function memory_get_peak_usage()
	{
		return 0;
	}
}

if (!function_exists('getmxrr')) {
	/**
	 * PHP 5.3 (added to Windows)
	 * @param string $hostname hostname
	 * @param array  &$mxhosts mxhosts
	 *
	 * @return bool
	 */
	function getmxrr($hostname, &$mxhosts)
	{
		$mxhosts = array();
		exec('nslookup.exe -q=mx '.escapeshellarg($hostname), $result_arr);
		foreach ($result_arr as $line) {
			if (preg_match("/.*mail exchanger = (.*)/", $line, $matches)) {
				$mxhosts[] = $matches[1];
			}
		}
		$return = count($mxhosts) > 0;
		return $return;
	}
}
