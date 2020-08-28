<?php

namespace bdk;

use bdk\Debug\Utility\Utf8;

/**
 * String methods
 */
class Str
{

	public static $temp;

	/**
	 * explode tab/csv delimited string (aka csv row) to array
	 *
	 * A polyfill for str_getcsv (php 5>= 5.3.0)
	 *
	 * @param string  $delim    delimiter (',')
	 * @param string  $str      string
	 * @param string  $enclose  enclosure (")
	 * @param boolean $preserve preserve enclosure (false)
	 *
	 * @return array
	 */
	public static function explodeDelim($delim = ',', $str = '', $enclose = '"', $preserve = false)
	{
		$resArr = array();
		$count = 0;
		$expEncArr = \explode($enclose, $str);
		foreach ($expEncArr as $EncItem) {
			if ($count++ % 2) {
				\array_push($resArr, \array_pop($resArr) . ($preserve ? $enclose : '') . $EncItem . ($preserve ? $enclose : ''));
			} else {
				$expDelArr = \explode($delim, $EncItem);
				\array_push($resArr, \array_pop($resArr) . \array_shift($expDelArr));
				$resArr = \array_merge($resArr, $expDelArr);
			}
		}
		return $resArr;
	}

	/**
	 * convert to/from user friendly "1.21 kB"
	 * 1234       => '1.21 kB'
	 * '1.21 kB'  => 1239
	 *
	 * @param integer|string $size   string/int
	 * @param boolean        $retInt (false)
	 *
	 * @return string|integer returns int when retInt == true
	 */
	public static function getBytes($size, $retInt = false)
	{
		if (\preg_match('/^([\d,.]+)\s?([kmgtp])b?$/i', $size, $matches)) {
			$size = \str_replace(',', '', $matches[1]);
			switch (\strtolower($matches[2])) {
				case 'p':
					$size *= 1024;
					// no break
				case 't':
					$size *= 1024;
					// no break
				case 'g':
					$size *= 1024;
					// no break
				case 'm':
					$size *= 1024;
					// no break
				case 'k':
					$size *= 1024;
			}
		}
		if (!$retInt) {
			$units = array('B','kB','MB','GB','TB','PB');
			$pow = \pow(1024, ($i = \floor(\log($size, 1024))));
			$size = $pow == 0
				? '0 B'
				: \round($size / $pow, 2) . ' ' . $units[$i];
		} else {
			$size = \round($size);
		}
		return $size;
	}

	/**
	 * Checks if a given string is base64 encoded
	 *
	 * @param string $str string to check
	 *
	 * @return boolean
	 */
	public static function isBase64Encoded($str)
	{
		return \bdk\Debug\Utility::isBase64Encoded($str);
	}

	/**
	 * Is the passed string a URL?
	 *
	 * @param string $url  string/URL to test
	 * @param array  $opts options
	 *
	 * @return boolean
	 */
	public static function isUrl($url, $opts = array())
	{
		$opts = \array_merge(array(
			'requireSchema' => true,
		), $opts);
		$urlRegEx = '@^'
			. ($opts['requireSchema']
				? 'https?://'
				: '(https?://)?'
				)
			. '[-\w\.]+'	// hostname
			. '(:\d+)?'		// port
			// .'(/([-\w/\.]*(\?\S+)?)?)?@';
			. '('
				. '/'
				. '('
					. '[-\w/\.,%:]*'   // path
					. '(\?\S+)?'       // query
					. '(#\S*)?'        // fragment/hash
				. ')?'
			. ')?'
			. '$@';
		return \preg_match($urlRegEx, $url) > 0;
	}

	/**
	 * Check if string is UTF-8 encoded
	 *
	 * @param string $str string to check
	 *
	 * @return boolean
	 */
	public static function isUtf8($str)
	{
		return Utf8::isUtf8($str);
	}

	/**
	 * add the ordinal suffix (st,nd,rd,th)
	 *
	 * @param integer $num the number to ordinalify
	 *
	 * @return string
	 */
	public static function ordinal($num)
	{
		$ext = 'th';
		if ($num < 11 || $num > 13) {
			switch ($num % 10) {
				case 1:
					$ext = 'st';
					break;
				case 2:
					$ext = 'nd';
					break;
				case 3:
					$ext = 'rd';
			}
		}
		return $num . $ext;
	}

	/**
	 * like PHP's parse_str()
	 *   key difference: optionaly converts root key dots and spaces to '_'
	 *
	 * @param string $str  input string
	 * @param array  $opts parse options
	 *
	 * @return array
	 */
	public static function parse($str, $opts = array())
	{
		$params = array();
		$opts = \array_merge(
			array(
				'conv_dot'		=> false,
				'conv_space'	=> true,
			),
			$opts
		);
		if (
            ($opts['conv_dot'] || \strpos($str, '.') === false)
			&& ($opts['conv_space'] || \strpos($str, ' ') === false)
		) {
			// just use parse_str
			\parse_str($str, $params);
		} else {
			$pairs = \explode('&', $str);
			foreach ($pairs as $pair) {
				$ptr = &$params;
				$pair = \array_map('urldecode', \explode('=', $pair));
				$path = $pair[0];
				$v = isset($pair[1])
					? $pair[1]
					: '';
				$i = 0;
				$re = '/^([^\[]*)/';
				while (\preg_match($re, $path, $matches)) {
					$k = $matches[1];
					$a = \preg_match('/^\s?$/', $k);		// 0 or 1 whitepace = append
					if ($i == 0) {
						// ltrim spaces as parse_str() does
						$k = \ltrim($k, ' ');
						if (\strlen($k) == 0) {
							break;
						}
						if ($opts['conv_space']) {
							$k = \str_replace(' ', '_', $k);
						}
						$re = '/^\[([^\]]*?)\](\s*)/';	// change regex
					} elseif (!empty($matches[2])) {
						$path = '';	// space on the end... this is the end of the path
					}
					$path = \substr($path, \strlen($matches[0]));	// remaining path
					if ($path !== false) {
						if ($a) {
							$ptr[] = array();
							\end($ptr);
							$k = \key($ptr);
						} elseif (!isset($ptr[ $k ]) || \is_string($ptr[ $k ])) {
							$ptr[ $k ] = array();
						}
						$ptr = &$ptr[$k];
					} else {
						if ($a) {
							$ptr[] = $v;
						} else {
							$ptr[ $k ] = $v;
						}
						break;
					}
					$i++;
				}
				if (!empty($path)) {
					// still have remaining path... there were unmatched brackets
					$ptr = $v;
				}
			}
		}
		return $params;
	}

	/**
	 * puralize (add an s) to a string when necessary
	 *
	 * @param integer $count  how many
	 * @param string  $single non plural version
	 * @param string  $plural optional plural form (if not a matter or simply adding an s)
	 *
	 * @return string
	 */
	public static function plural($count, $single, $plural = null)
	{
		if (\is_null($plural)) {
			$plural = $single . 's';
		}
		return $count == 1
			? $single
			: $plural;
	}

	/**
	 * "Quick" Templater
	 *
	 * @param string       $template template
	 * @param array|object $values   values
	 *
	 * @return string
	 */
	public static function quickTemp($template = '', $values = array())
	{
		if (!\is_array($values) && !\is_object($values)) {
			$values = \func_get_args();
			\array_shift($values);
		}
		self::$temp = array(
            'count' => 0,
            'tokensKeep' => array(),
            'values' => $values,
        );
		// $regex = '/\\\\?[<\[](::|%)\s*(.*?)\s*\\1[>\]]/';
		$regex = '@\\\\?(?:
        \{\{\s*(.*?)\s*\}\}|           # {{a}}
        \[::\s*(.*?)\s*::\]            # [::a::]
        # <::\s*(.*?)\s*::>|           # <::a::>
        # <!--::\s*+(.*?)\s*+::-->|    # <!--::a::-->
        # \/\*::\s*(.*?)\s*::\*\/      # /*::a::*/
        )@sx';
        $return = $template;
		do {
            self::$temp['count'] = 0;
            $return = \preg_replace_callback($regex, array(__CLASS__, 'quickTempCallback'), $return);
        } while (self::$temp['count']);
        $return = \strtr($return, self::$temp['tokensKeep']);
		self::$temp = null;
		return $return;
	}

	/**
	 * Callback used by self::quickTemp's regex callback
	 *
	 * @param string $matches preg_replace_callback's matches
	 *
	 * @return string`
	 */
	private static function quickTempCallback($matches)
	{
		for ($i = 1, $count = \count($matches); $i < $count; $i++) {
			if (\strlen($matches[$i])) {
				$key = $matches[$i];
				break;
			}
		}
        self::$temp['count'] ++;
		$return = null;
        if (\substr($matches[0], 0, 1) == '\\') {
			// ie \{{token}}
            // don't count this as a match / replacement
            self::$temp['count'] --;
            self::$temp['tokensKeep'][$matches[0]] = \ltrim($matches[0], '\\');
            return $matches[0];
		} elseif (\is_array(self::$temp['values'])) {
			$arrayUtil = new ArrayUtil();
			$return = $arrayUtil->path(self::$temp['values'], $key);
		} elseif (\is_object(self::$temp['values']) && isset(self::$temp['values']->$key)) {
			$return = self::$temp['values']->$key;
		}
		if ($return === null) {
			#$this->debug->log('try lookup func');
			$regex = '/^(\S+?)(?:'	// key
					. '\((.+)\)|'	// (param1, ...)
					. '\s+((?:[\'"\$]|array|true|false|null).*)$'	// params not inside parens
				. ')/s';
			if (\preg_match($regex, $key, $matches)) {
				#$this->debug->log('func()');
				$args = !empty($matches[2])
					? $matches[2]
					: $matches[3];
				$key = $matches[1];
				$parsed = @eval('$args = array(' . $args . ');');
				if ($parsed === false) {
					// workaround for PHP "Bug" that sends a 500 response when an eval doesn't parse
					$code = !empty($_SERVER['REDIRECT_STATUS'])
						? $_SERVER['REDIRECT_STATUS']
						: 200;
					\header('Status:', true, $code);
				}
			} else {
				#$this->debug->log('unquoted string arg');
				$args	= \explode(' ', $key, 2);
				$key	= \array_shift($args);
			}
			#$this->debug->log('args', $args);
			if (\is_object(self::$temp['values']) && \method_exists(self::$temp['values'], 'get' . \ucfirst($key))) {
				$method = 'get' . \ucfirst($key);
				#$this->debug->info('method exists', $method);
				$return = \call_user_func_array(array(self::$temp['values'], $method), $args);
			} elseif (\function_exists('get_' . $key) && !\in_array($key, array('class'))) {
				$return = \call_user_func_array('get_' . $key, $args);
			}
		}
		#$this->debug->info('return', $return);
		return $return;
	}

	/**
	 * replace accented chars with their non-accented equiv
	 *
	 * @param string $str string
	 *
	 * @return string
	 * @see    http://stackoverflow.com/questions/1017599/how-do-i-remove-accents-from-characters-in-a-php-string
	 */
	public static function removeAccents($str)
	{
		// $from_chars = explode(',', 'À,Á,Â,Ã,Ä,Å,Ç,È,É,Ê,Ë,Ì,Í,Î,Ï,Ð,Ñ,Ò,Ó,Ô,Õ,Ö,Ø,Ǿ,Ù,Ú,Û,Ü,Ý,ß,à,á,â,ã,ä,å,ç,è,é,ê,ë,ì,í,î,ï,ð,ñ,ò,ó,ô,õ,ö,ø,ǿ,ù,ú,û,ü,ý,ÿ');
		// $to_chars	= str_split('AAAAAACEEEEIIIIDNOOOOOOOUUUUYBaaaaaaceeeeiiiionooooooouuuuyy');
		// $str = str_replace($from_chars, $to_chars, $str);
        $map = array(
			// single letters
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'ą' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ǎ' => 'a', 'ǻ' => 'a',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Ą' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ǎ' => 'A', 'Ǻ' => 'A',
			'ß' => 'B',
			'ç' => 'c', 'ć' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'č' => 'c',
			'Ç' => 'C', 'Ć' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Č' => 'C',
			'ď' => 'd', 'đ' => 'd',
			'Ð' => 'D', 'Ď' => 'D', 'Đ' => 'D',
			'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ę' => 'e', 'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ě' => 'e',
			'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ę' => 'E', 'Ē' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ě' => 'E',
			'ƒ' => 'f',
			'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g',
			'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G',
			'ĥ' => 'h', 'ħ' => 'h', 'Ĥ' => 'H', 'Ħ' => 'H',
			'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
			'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ſ' => 'i', 'ǐ' => 'i', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ĩ' => 'I', 'Ī' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I', 'Ǐ' => 'I',
			'ĵ' => 'j', 'Ĵ' => 'J',
			'ķ' => 'k', 'Ķ' => 'K',
			'ł' => 'l', 'ĺ' => 'l', 'ļ' => 'l', 'ľ' => 'l', 'ŀ' => 'l',
			'Ł' => 'L', 'Ĺ' => 'L', 'Ļ' => 'L', 'Ľ' => 'L', 'Ŀ' => 'L',
			'ñ' => 'n', 'ń' => 'n', 'ņ' => 'n', 'ň' => 'n', 'ŉ' => 'n',
			'Ñ' => 'N', 'Ń' => 'N', 'Ņ' => 'N', 'Ň' => 'N',
			'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ð' => 'o', 'ø' => 'o', 'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o', 'ơ' => 'o', 'ǒ' => 'o', 'ǿ' => 'o',
			'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ŏ' => 'O', 'Ő' => 'O', 'Ơ' => 'O', 'Ǒ' => 'O', 'Ǿ' => 'O',
			'ŕ' => 'r', 'ŗ' => 'r', 'ř' => 'r',
			'Ŕ' => 'R', 'Ŗ' => 'R', 'Ř' => 'R',
			'ś' => 's', 'š' => 's', 'ŝ' => 's', 'ş' => 's',
			'Ś' => 'S', 'Š' => 'S', 'Ŝ' => 'S', 'Ş' => 'S',
			'ţ' => 't', 'ť' => 't', 'ŧ' => 't',
			'Ţ' => 'T', 'Ť' => 'T', 'Ŧ' => 'T',
			'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u', 'ư' => 'u', 'ǔ' => 'u', 'ǖ' => 'u', 'ǘ' => 'u', 'ǚ' => 'u', 'ǜ' => 'u',
			'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ũ' => 'U', 'Ū' => 'U', 'Ŭ' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U', 'Ư' => 'U', 'Ǔ' => 'U', 'Ǖ' => 'U', 'Ǘ' => 'U', 'Ǚ' => 'U', 'Ǜ' => 'U',
			'ŵ' => 'w',
			'Ŵ' => 'W',
			'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y',
			'Ý' => 'Y', 'Ÿ' => 'Y', 'Ŷ' => 'Y',
			'ż' => 'z', 'ź' => 'z', 'ž' => 'z',
			'Ż' => 'Z', 'Ź' => 'Z', 'Ž' => 'Z',
			// accentuated ligatures
			'Ǽ' => 'A',
			'ǽ' => 'a',
        );
		$str = self::toUtf8($str);
        $str = \strtr($str, $map);
		return $str;
	}

    /**
     * convert string to camel case
     *
     * @param string $str string to convert
     *
     * @return string
     */
    public static function toCamelCase($str)
    {
		/*
        do {
			\preg_match_all('/([a-zA-Z])([A-Z])/', $str, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$str = \str_replace($match[0], $match[1].' '.$match[2], $str);
			}
		} while ($matches);
		$str = \strtolower($str);
		$str = \str_replace('_', ' ', $str);
		$str = \ucwords($str);
		$str = \str_replace(' ', '', $str);
		$str[0] = \strtolower($str[0]);
        */
        return \preg_replace_callback('#_([a-z0-9])#', function ($matches) {
            return \strtoupper($matches[1]);
        }, $str);
	}

	/**
	 * Convert string to UTF-8 encoding
	 *
	 * @param string $str string to convert
	 *
	 * @return string
	 */
	public static function toUtf8($str)
	{
		/*
		if (extension_loaded('mbstring')) {
			$encoding = mb_detect_encoding($str, mb_detect_order(), true);
			if ($encoding == 'UTF-8' && !self::isUtf8($str)) {
				$encoding = false;
			}
			if (!$encoding) {
				// unknown encoding... likely ANSI or Windows-1252
				$str_conv = false;
				if (function_exists('iconv')) {
					#$this->debug->log('converting using iconv()');
					$str_conv = @iconv('cp1252', 'UTF-8', $str);	// was cp1251
				}
				if ($str_conv === false) {
					$html = new Html();
					// use htmlentities -> htmlentity_decode()
					$str_conv = $html->htmlentities($str);
					$str_conv = html_entity_decode($str_conv, ENT_COMPAT, 'UTF-8');
				}
				$str = $str_conv;
			} elseif (!in_array($encoding, array('ASCII','UTF-8'))) {
				$str = mb_convert_encoding($str, 'UTF-8', $encoding);
			}
		} else {
			#$this->debug->warn('mbstring not installed - no conversion performed');
		}
		*/
		return Utf8::toUtf8($str);
	}
}
