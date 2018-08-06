<?php
/**
 * Date/Time related methods
 *
 * Notes:
 *  unix timestamps are always GMT
 *		funcs that create/format timestamp take local timezone in to consideration
 *	date, mktime, strtotime, etc have 32-bit limitation
 *		valid range PHP >= 5.1 : Fri, 13 Dec 1901 20:45:54 GMT - Tue, 19 Jan 2038 03:14:07 GMT
 *		valid range PHP < 5.1 : 01-01-1970 00:00:00 GMT - Tue, 19 Jan 2038 03:14:07 GMT
 *		date & getdate
 *			do not throw error or return false when passed a timestamp that is out of range,
 *			rather, the date is wrapped for unexpected results
 *		mktime & strtotime
 *			return false or -1 depending on version of php
 *	DateTime class (PHP >= 5.2) does not have 32-bit limitation
 *		DateTime::add and DateTime:sub (php >= 5.3) are capable of doing arithmetic outside the 32-bit range
 */

namespace bdk;

/**
 * DateTime
 */
class DateTime
{

	private $debug = null;
	private $sysInfo;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->debug = \bdk\Debug::getInstance();
		$this->sysInfo = $this->sysInfo();
	}

	/**
	 * Convrt datetime string to timestamp
	 *
	 * @param string $datetime datetime string
	 *
	 * @return int timestamp
	 */
	public function datetimeToTs($datetime)
	{
		#$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $datetime);
		$keys = array('ye', 'mo', 'da', 'hr', 'mi', 'se');
		$values = sscanf($datetime, '%4s%2s%2s%2s%2s%2s');
		$parts = array();
		foreach ($keys as $i => $k) {
			$parts[$k] = $values[$i];
		}
		#$this->debug->log('parts', $parts);
		$ts = mktime($parts['hr'], $parts['mi'], $parts['se'], $parts['mo'], $parts['da'], $parts['ye']);
		#$this->debug->log('ts', date('Ymd', $ts));
		#$this->debug->groupEnd();
		return $ts;
	}

	/**
	 * Converts format used by date() to one that can be used by strftime()
	 *
	 * @param string $dateFormat format used by date()
	 *
	 * @return string
	 * @link http://www.php.net/manual/en/function.strftime.php
	 */
	public function dateToStrftime($dateFormat)
	{
		$caracs = array(
			// Day - no strf eq : S
			'd' => '%d', 'D' => '%a', 'j' => '%e', 'l' => '%A', 'N' => '%u', 'w' => '%w', 'z' => '%j',
			// Week - no date eq : %U, %W
			'W' => '%V',
			// Month - no strf eq : n, t
			'F' => '%B', 'm' => '%m', 'M' => '%b',
			// Year - no strf eq : L; no date eq : %C, %g
			'o' => '%G', 'Y' => '%Y', 'y' => '%y',
			// Time - no strf eq : B, G, u; no date eq : %r, %R, %T, %X
			'a' => '%P', 'A' => '%p', 'g' => '%l', 'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S',
			// Timezone - no strf eq : e, I, P, Z
			'O' => '%z', 'T' => '%Z',
			// Full Date / Time - no strf eq : c, r; no date eq : %c, %D, %F, %x
			'U' => '%s'
		);
		return strtr((string)$dateFormat, $caracs);
	}

	/**
	 * Equivalent to getdate()...  attempts to use DateTime class to avoid 32-bit limitation
	 *
	 * @param integer $ts timestamp
	 *
	 * @return array
	 */
	public function getdate($ts)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$sysInfo = &$this->sysInfo;
		$return = array();
		if ($sysInfo['DateTime']) {
			$datetime = new \DateTime;
			$datetime->setTimestamp($ts);
			$return = array(
				'seconds'	=> $datetime->format('s'),	// 0-59
				'minutes'	=> $datetime->format('i'),	// 0-59
				'hours'		=> $datetime->format('H'),	// 0-23
				'mday'		=> $datetime->format('s'),	// 1-31
				'wday'		=> $datetime->format('j'),	// 0-6
				'mon'		=> $datetime->format('n'),	// 1-12
				'year'		=> $datetime->format('Y'),	// 4-digit year
				'yday'		=> $datetime->format('z'),	// 0-365
				'weekday'	=> $datetime->format('l'),	// Sunday -> Saturday
				'month'		=> $datetime->format('F'),	// January through December
				'0'			=> $ts,
			);
		} else {
			$return = getdate($ts);
			if ($ts < $sysInfo['ts_min'] || $ts > $sysInfo['ts_max']) {
				trigger_error(__FUNCTION__.'() timestamp out of 32-bit range', E_USER_WARNING);
			}
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Given passed timestamp, return timestamp of next business day
	 *
	 * @param integer $ts       timestamp
	 * @param array   $holidays = array()  optional array of dates (formatted yyyymmdd) that will be skipped
	 *
	 * @return int timestamp
	 */
	public function getNextBusinessDay($ts, $holidays = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$date_parts = getdate($ts);
		// Add 1 day to begin with
		$ts = mktime(0, 0, 0, $date_parts['mon'], $date_parts['mday']+1, $date_parts['year']);
		$good_date = false;
		while ($good_date == false) {
			if ($date_parts['weekday'] == 'Saturday') {
				// Add 2 days
				$ts = mktime(0, 0, 0, $date_parts['mon'], $date_parts['mday']+2, $date_parts['year']);
			} elseif ($date_parts['weekday'] == 'Sunday') {
				// Add 1 day
				$ts = mktime(0, 0, 0, $date_parts['mon'], $date_parts['mday']+1, $date_parts['year']);
			} elseif (in_array(date('Ymd', $ts), $holidays)) {
				// Add 1 day
				$ts = mktime(0, 0, 0, $date_parts['mon'], $date_parts['mday']+1, $date_parts['year']);
			} else {
				$good_date = true;
			}
			if (!$good_date) {
				$date_parts = getdate($ts);
			}
		}
		$this->debug->groupEnd();
		return $ts;
	}

	/**
	 * find the start/end of the hour,day,week,month, or year that contains passed date/timestamp
	 *   also finds passed date/timestamp +/- same period
	 *
	 * @param mixed $time int timestamp, string date, or object DateTime
	 * @param array $opts options
	 *
	 * @return boolean|array false on failure, array on success
	 */
	public function getRange($time, $opts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$opts = array_merge(array(
			'range'		=> 'day',	// hour, day, week, month, or year
			'format'	=> 'U',		// 'U' = timestamp
			'week_start_day'=> 0,
		), $opts);
		#$this->debug->log('opts', $opts);
		$this->debug->log('range', $opts['range']);
		$return = false;
		$dt = null;	// DateTime obj
		$ts = null;	// timestamp
		$sysInfo = &$this->sysInfo;
		if ($sysInfo['DateTime::add'] && $sysInfo['DateTime::sub']) {
			#$this->debug->log('using DateTime class');
			if (is_object($time) && $time instanceof \DateTime) {
				$dt = $time;
			} elseif (is_string($time)) {
				$dt = new \DateTime($time);
			} elseif (is_int($time) || is_float($time)) {
				$dt = new \DateTime;
				$dt->setTimestamp($time);
			}
			$this->debug->log('dt', $dt->format('r'));
			if ($dt) {
				switch($opts['range']) {
					case 'hour':
						$str_start = $dt->format('Y-m-d H:00:00');
						$di = new \DateInterval('PT1H');
						break;
					case 'day':
						$str_start = $dt->format('Y-m-d');
						$di = new \DateInterval('P1D');
						break;
					case 'week':
						$str_day = $dt->format('Y-m-d');
						$wday = $dt->format('w') + $opts['week_start_day'];
						$dt_temp = new \DateTime($str_day);
						$dt_temp->sub(new \DateInterval('P'.$wday.'D'));
						$str_start = $dt_temp->format('Y-m-d');
						$di = new \DateInterval('P1W');
						break;
					case 'month':
						$str_start = $dt->format('Y-m-01');
						$di = new \DateInterval('P1M');
						break;
					case 'year':
						$str_start = $dt->format('Y-01-01');
						$di = new \DateInterval('P1Y');
						break;
				}
				$dt_start = new \DateTime($str_start);
				$return = array(
					'passed'=> $dt->format($opts['format']),
					'start'	=> $dt_start->format($opts['format']),
					'end'	=> null,	// calc'd below to avoid modifying dt_start before
					'next'	=> $dt_start->add($di)->format($opts['format']),
					'minus'	=> $dt->sub($di)->format($opts['format']),
					'plus'	=> $dt->add($di)->add($di)->format($opts['format']),	// need to add back twice
				);
				$return['end'] = $dt_start->sub(new \DateInterval('PT1S'))->format($opts['format']);
			}
		} else {
			$this->debug->warn('using fallback strtotime/mktime');
			if (!$sysInfo['64bit']) {
				$this->debug->warn('have 32-bit restriction');
			}
			if (is_object($time) && $time instanceof \DateTime) {
				// DateTime class defined but no add/sub methods
				$ts = $time->format('U');
				if ($ts > $sysInfo['ts_max'] || $ts < $sysInfo['ts_min']) {
					$this->debug->warn('passed DateTime obj is out of 32-bit range');
					$ts = false;
				}
			} elseif (is_string($time)) {
				$ts = strtotime($time);
			} elseif ((int) $time == $time) {
				$ts = $time;
			}
			$this->debug->log('ts', $ts);
			if ($ts) {
				$ts_info = getdate($ts);
				switch($opts['range']) {
					case 'hour':
						$ts_start = mktime(
							0,
							0,
							$ts_info['hours'],
							$ts_info['mon'],
							$ts_info['mday'],
							$ts_info['year']
						);
						break;
					case 'day':
						$ts_start = mktime(0, 0, 0, $ts_info['mon'], $ts_info['mday'], $ts_info['year']);
						break;
					case 'week':
						$ts_start = mktime(
							0,
							0,
							0,
							$ts_info['mon'],
							$ts_info['mday'] - $ts_info['wday'] + $opts['week_start_day'],
							$ts_info['year']
						);
						break;
					case 'month':
						$ts_start = mktime(0, 0, 0, $ts_info['mon'], 1, $ts_info['year']);
						break;
					case 'year':
						$ts_start = mktime(0, 0, 0, 1, 1, $ts_info['year']);
						break;
				}
				$return = array(
					'passed'=> date($opts['format'], $ts),
					'start'	=> date($opts['format'], $ts_start),
					'end'	=> date($opts['format'], strtotime('+1 '.$opts['range'], $ts_start)-1),
					'next'	=> date($opts['format'], strtotime('+1 '.$opts['range'], $ts_start)),
					'minus'	=> date($opts['format'], strtotime('-1 '.$opts['range'], $ts)),
					'plus'	=> date($opts['format'], strtotime('+1 '.$opts['range'], $ts)),
				);
				if (strtotime('-1 '.$opts['range'], $ts) > strtotime('+1 '.$opts['range'], $ts)) {
					trigger_error(__FUNCTION__.'(): one or more calculated dates are out of 32-bit range', E_USER_WARNING);
					$return = false;
				}
			} else {
				trigger_error(__FUNCTION__.'(): unable to handle passed date - may be out of 32-bit range', E_USER_WARNING);
			}
		}
		if ($return && $opts['format'] == 'U') {
			foreach ($return as $k => $v) {
				$return[$k] = floatval($v);
			}
		}
		$this->debug->log('return', $return);
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Return a "3 hours ago" type string for passed timestamp
	 *
	 * @param integer $ts timestamp
	 *
	 * @return string
	 */
	public static function getRelTime($ts)
	{
		// $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		if (is_string($ts)) {
			// it's possible a timestamp was passed as a string
			$isTs = false;
			if (is_numeric($ts)) {
				// if not YYYYMMDD, assume ts
				$isTs = !preg_match('/^\d{4}(0[1-9]|1[0-2])[0-3][0-9]$/', $ts);
			}
			if (!$isTs) {
				$ts = strtotime($ts);
			}
		}
		$ret = '';
		$ts_now = time();
		$ts_mn = mktime(0, 0, 0);				// 12:00am today
		if ($ts > time()) {						// the future Conan?
			$ret = date('Y-m-d g:ia', $ts);
		} elseif ($ts_now - $ts < 60) {			// within a min
			$ret = 'Just now';
		} elseif ($ts_now - $ts < 3600) {		// within the hour
			$ret = round(($ts_now-$ts)/60) .' min ago';
		} elseif ($ts_now - $ts < 3600*6) {		// within 6 hours
			$hrs = round(($ts_now-$ts)/3600);
			// $strObj = new String();
			$ret = $hrs.' '.Str::plural($hrs, 'hour').' ago';
		} elseif ($ts > $ts_mn) {				// since midnight so it's today
			$ret = 'Today at '.date('g:ia', $ts);
		} elseif ($ts > $ts_mn - 86400) {		// since midnight 1 day ago so it's yesterday
			$ret = 'Yesterday at '.date('g:ia', $ts);
		} elseif ($ts > $ts_mn - 86400*7) {		// since midnight 7 days ago so it's within 1 week
			$ret = date('l \a\t g:ia', $ts);
		} elseif ($ts > mktime(0, 0, 0, 1, 1)) {// since 1st Jan so it's this year
			$ret = date('F jS', $ts);
		} else {								// last year..
			$ret = date('F jS, Y', $ts);
		}
		#$this->debug->log('return', $ret);
		// $this->debug->groupEnd();
		return $ret;
	}

	/**
	 * Equivalent (almost) to mktime()...  attempts to use DateTime class to avoid 32-bit limitation
	 *	difference:  year is always assumed to be 4-digit
	 *  to pass a 2-digit year, pass it as a string
	 *
	 * @param integer     $hr hour
	 * @param integer     $mi min
	 * @param integer     $se sec
	 * @param integer     $mo month
	 * @param integer     $da day
	 * @param integer|str $ye year
	 *
	 * @return integer|float $timestamp
	 */
	public function mktime($hr = null, $mi = null, $se = null, $mo = null, $da = null, $ye = null)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$ts = false;
		$sysInfo = &$this->sysInfo;
		if ($sysInfo['DateTime::add']) {
			$dt = new \DateTime('now');
			$vals_default = array(
				'hr'	=> $dt->format('H'),
				'mi'	=> $dt->format('i'),
				'se'	=> $dt->format('s'),
				'mo'	=> $dt->format('m'),
				'da'	=> $dt->format('d'),
				'ye'	=> $dt->format('Y'),
			);
			$interval_map = array(
				'mo'	=> 'P%sM',
				'da'	=> 'P%sD',
				'hr'	=> 'PT%sH',
				'mi'	=> 'PT%sM',
				'se'	=> 'PT%sS',
			);
			$args = func_get_args();
			$keys = array_keys($vals_default);
			$vals = array();
			foreach ($args as $i => $v) {
				if ($i < count($vals_default)) {
					$k = $keys[$i];
					$vals[$k] = $v;
				}
			}
			$vals = array_merge($vals_default, $vals);
			if (is_string($vals['ye'])
				&& strlen($vals['ye']) < 3
				&& $vals['ye'] < 100
				&& $vals['ye'] >= 0
			) {
				// "00" - "99" get treated as a 2-digit year
				//		0-69 map to 2000-2069 and 70-100 map to 1970-2000.
				$y = $vals['ye'];
				if ($y == 0) {
					$y = 2000;
				} elseif ($y < 70) {
					$y += 2000;
				} else {
					$y += 1900;
				}
				$vals['ye'] = $y;
			}
			// set the datetime to the very begining of passed year
			$dt->setDate($vals['ye'], 1, 1);
			$dt->settime(0, 0, 0);
			// $is = interval string
			foreach ($interval_map as $k => $is) {
				$sub_or_add = 'add';
				if (in_array($k, array('mo', 'da'))) {
					$vals[$k]--;
				}
				if ($vals[$k] < 0) {
					$sub_or_add = 'sub';
					$vals[$k] = abs($vals[$k]);
				} elseif ($vals[$k] == 0) {
					continue;
				}
				$is = sprintf($is, $vals[$k]);
				$this->debug->log($k, $sub_or_add, $is);
				$di = new \DateInterval($is);
				if ($sub_or_add == 'add') {
					$dt->add($di);
				} else {
					$dt->sub($di);
				}
			}
			$ts = floatval($dt->format('U'));
			$this->debug->log('ts', $ts, $dt->format('r'));
		} else {
			$this->debug->log('using mktime()');
			$ts = mktime($hr, $mi, $se, $mo, $da, $ye);
		}
		$this->debug->groupEnd();
		return $ts;
	}

	/**
	 * Get date/time related system info
	 *
	 * @return array
	 */
	public function sysInfo()
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$dtMethods = class_exists('\DateTime')
			? get_class_methods('\DateTime')
			: array();
		$x64 = defined('PHP_INT_SIZE') && PHP_INT_SIZE == 8;
		$info = array(
			'64bit'			=> $x64,
			'DateTime'		=> class_exists('\DateTime'),
			'DateTime::add' => in_array('add', $dtMethods),
			'DateTime::sub' => in_array('sub', $dtMethods),
			'ts_min'		=> $x64
								? null
								: ( version_compare(PHP_VERSION, '5.1', '>=')
										? pow(-2, 31)+2
										: 0
									),
			'ts_max'		=> $x64
								? null
								: pow(2, 31)-1
		);
		#$this->debug->log('sysInfo', $info);
		#$this->debug->log('ts_min', date('r', $info['ts_min']));
		#$this->debug->log('ts_max', date('r', $info['ts_max']));
		$this->debug->groupEnd();
		return $info;
	}

	/**
	 * Equivalent to strtotime()...  attempts to use DateTime class to avoid 32-bit limitation
	 *
	 * @param string  $str          date/time
	 * @param integer $ts_rel_start relative timestamp
	 *
	 * @return integer|float $timestamp
	 */
	public function strtotime($str, $ts_rel_start = null)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$sysInfo = &$this->sysInfo;
		#$this->debug->log('sysInfo', $sysInfo);
		if ($sysInfo['DateTime']) {
			$dt_a = new \DateTime($str);
			if (!is_null($ts_rel_start)) {
				// param 1 could be an absolute (not relative) time...
				// use modify and see if the value changes
				//    if changes, it's a relative time
				//    if doesn't change it's absolute
				/*
				$dt_now = new \DateTime('now');
				$dt_b = new \DateTime('@'.$ts_rel_start);
				$diff_now_a	= $dt_now->diff($dt_a);
				$diff_now_b = $dt_now->diff($dt_b);
				$debug_format = '%r%yY %r%mm %r%dd %r%hh %r%ii %r%ss';
				$this->debug->log('diff_now_a', $diff_now_a);
				$this->debug->log('diff_now_b', $diff_now_b);
				$this->debug->log('diff_now_a', $diff_now_a->format($debug_format));
				$this->debug->log('diff_now_b', $diff_now_b->format($debug_format));
				*/
			}
			$ts = floatval($dt_a->format('U'));
		} else {
			$this->debug->log('using strtotime()');
			$ts = strtotime($str);
			if ($ts === false || $ts == -1 && version_compare(PHP_VERSION, '5.1', '<')) {
				$ts = time();
				trigger_error(__FUNCTION__.'() unable to parse date or date out of 32-bit range', E_USER_WARNING);
			}
		}
		$this->debug->groupEnd();
		return $ts;
	}
}
