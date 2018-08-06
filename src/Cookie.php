<?php

namespace bdk;

use bdk\Config;
// use bdk\Str;

/**
 * Cookie
 */
class Cookie extends Config
{

	/*
		Note that if a cookie is flagged as secure, it will not be passed back to non-secure server
		... and non-secure server won't be able to overwrite/send/update value
	*/
	static protected $cfgStatic = array(
		'expiry'		=> 2592000,	// 30-days (60*60*24*30)   note that this is specified as a TTL (relative time)
		'path'			=> '/',
		'secure'		=> false,
		'httponly'		=> false,
		'hashKey'		=> null,	// set to secret
	);

	/**
	 * Return cookie value
	 *
	 * @param string $name    cookie name
	 * @param mixed  $default value if not set
	 *
	 * @return mixed
	 */
	public static function get($name, $default = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__METHOD__, $name);
		if (!isset($_COOKIE[$name])) {
			$debug->groupEnd();
			return $default;
		}
		$value = $_COOKIE[$name];
		if (is_array($value)) {
			$debug->groupEnd();
			return $value;
		}
		if ($value == 'deleted') {
			unset($_COOKIE[$name]);
			$debug->groupEnd();
			return $default;
		}
		/*
		$tempValue = $value;
		if (Str::isBase64Encoded($value)) {
			$debug->log('testing base64 decoded for serialized data');
			$tempValue = base64_decode($value);
		}
		$debug->log('tempValue', $tempValue);
		*/
		$hashJsonRegex = '/^(?:([0-9a-f]{32}):)?((\{.+\})|(\[.+\]))$/';
		if (preg_match($hashJsonRegex, $value, $matches)) {
			// appears to be a json'd array (hash optional)
			$hash = $matches[1];
			$jsonStr = $matches[2];
			// $debug->log('jsonStr', $jsonStr);
			if (self::$cfgStatic['hashKey']) {
				// should be hashed
				$hashComputed = md5(self::$cfgStatic['hashKey'].$jsonStr);
				if (!$hash || $hash !== $hashComputed) {
					// hash missing or invalid -> reject
					$debug->warn('invalid hash');
					$jsonStr = null;
					$value = null;
				}
			}
			if ($jsonStr) {
				$value = json_decode($jsonStr, true);
			}
		}
		$_COOKIE[$name] = $value;
		$debug->log(
			'returning cookieValue',
			is_string($value)
				? substr($value, 0, 80).'...'
				: $value
		);
		$debug->groupEnd();
		return $value;
	}

	/**
	 * Returns the coocie value that's received in the header
	 *
	 * @param name $name name of cookie
	 *
	 * @return   string
	 * @internal
	 */
	public static function getRaw($name)
	{
		$value = null;
		$rawCookies = !empty($_SERVER['HTTP_COOKIE'])
			? explode('; ', $_SERVER['HTTP_COOKIE'])
			: array();
		foreach ($rawCookies as $kv) {
			$pair = explode('=', $kv, 2);
			$k = array_shift($pair);
			$v = array_shift($pair);
			$k = str_replace(' ', '_', urldecode($k));
			if ($k == $name) {
				$value = urldecode($v);
				break;
			}
		}
		return $value;
	}

	public static function remove($name)
	{
		return $this->set(array(
			'name' => $name,
			'expiry' => -3600*24,
		));
	}

	/**
	 * A wrapper for PHP's setcookie()
	 * will accept an array as a value -> will be serialized
	 * can pass lifetime or expiry
	 * accepts:
	 *  mixed value, string name
	 *  mixed value, array params
	 *  array params
	 *
	 * @param array $params kev/value params used by setcookie()
	 *
	 * @return boolean
	 */
	public static function set($params)
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__METHOD__, $params);
		$return = true;
		$params = self::cookieParams(func_get_args());
		$cookieWas = isset($_COOKIE[$params['name']])
			? $_COOKIE[$params['name']]
			: null;
		$_COOKIE[$params['name']] = $params['value'];	// keep cookie array up-to-date
		if (is_array($params['value'])) {
			foreach ($params['value'] as $k => $v) {	// remove empty values
				if (empty($v) && !is_numeric($v)) {		// $v !== 0
					unset($params['value'][$k]);
				}
			}
		}
		$sendCookie = true;
		if (empty($params['value']) && $params['value'] !== 0) {
			$cookieRaw = self::getRaw($params['name']);
			if (!empty($cookieWas) || $cookieRaw !== null) {
				$debug->log('send delete cookie');
				$params['value']	= '';
				$params['expiry']	= time()-3600*24;       // the past
				unset($_COOKIE[$params['name']]);
			} else {
				$sendCookie = false;
			}
		} elseif (is_array($params['value'])) {
			$debug->log('serializing');
			$serialized = json_encode($params['value']);
			$hashKey = self::$cfgStatic['hashKey'];
			if ($hashKey) {
				$debug->log('adding hash');
				$hashComputed = md5($hashKey.$serialized);
				$serialized = $hashComputed.':'.$serialized;
			}
			$params['value'] = $serialized;
		}
		if ($sendCookie) {
			if (headers_sent()) {
				$debug->warn('headers have already been sent');
				$return = false;
			} else {
				// setcookie() urlencodes the value
				$return = setcookie(
					rawurlencode($params['name']),
					$params['value'],
					$params['expiry'],
					$params['path'],
					$params['domain'],
					$params['secure'],
					$params['httponly']
				);
				$debug->log($params['name'].' '.($return?'was':'not').' sent');
				$debug->log('cookie expires '.($params['expiry'] == 0
					? 'at end of session'
					: 'in '.($params['expiry']-time())/(60*60).' hour(s)'
				));
			}
		}
		$debug->groupEnd();
		return $return;
	}

	/**
	 * return cookie parameters
	 *
	 * @param array $args as passed to setCookieValue
	 *
	 * @return array
	 */
	protected static function cookieParams($args = array())
	{
		$params = array();
		if (count($args) == 1) {
			if (is_array($args[0])) {
				$params = $args[0];
			} else {
				$params['value'] = $args[0];
			}
		} elseif (count($args) == 2) {
			if (is_string($args[1])) {
				$params['name'] = $args[1];
			} else {
				$params = $args[1];
			}
			$params['value'] = $args[0];
		}
		$domainRegex = '/([^.]+\.[a-z]{2,})(:\d{1,4})?$/i';
		$paramsDefault = array(
			// 'name'		=> 'vars',
			'value'		=> '',
			// 'lifetime'	=> null,				// can pass lifetime in lieu of expiry (ala session_set_cookie_params)
												//		lifetime param takes precedence
			'domain'	=> preg_match($domainRegex, $_SERVER['HTTP_HOST'], $matches)
						? '.'.$matches[1]
						: '',
			/*
			'expiry'	=> time()+60*60*24*30,	// 30 Days in future;  set to zero to expire when browser closes
			'path'		=> '/',
			'secure'	=> false,
			'httponly'	=> false,
			*/
		);
		$params = array_merge($paramsDefault, self::$cfgStatic, $params);
		if (isset($params['lifetime'])) {
			$params['expiry'] = time() + $params['lifetime'];
		} elseif ($params['expiry'] < time() && $params['expiry'] != 0) {
			// treat as TTL
			$params['expiry'] += time();
		}
		return $params;
	}
}
