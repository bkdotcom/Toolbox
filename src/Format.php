<?php

namespace bdk;

use bdk\Geo\Address;
use bdk\ArrayUtil;

/**
 * Format data
 */
class Format
{

	private $debug;
	private $cfg = array(
		'date_format'	=> 'Y-m-d',
		// 'date_format' => 'F jS, Y',
		'tel_opts'	=> array(
			'display'		=> null,	// E.123 : http://en.wikipedia.org/wiki/E.123
			'value'			=> null,	// E.164 : http://en.wikipedia.org/wiki/E.164
			'type'			=> null,
			'markup'		=> false,	// use hcard markup and incl type if provided
			'nowrap'		=> true,	// wrap phone number in <span class="nowrap"> ?
										//    only applies when markup and link eval as false
			'link'			=> false,	// true, false, or 'auto'  ('auto' will create link for mobile client)
			'link_schema'	=> 'tel',
			'link_template'	=> '<a class="[::class::]" href="[::href::]">[::display::]</a>',
			'type_separator' => ', ',		// only separates types that are displayed
			'type_end'		=> ': ',
			'type_templates' => array(
				//  VOICE is excluded... will not be output as it is the default
				array(
					'types'		=> array('PREF'),
					'visible'	=> false,
					'template'	=> '<span class="type" style="display:none;">[::0::]</span>',
				),
				array(
					'types'		=> array('BBS', 'CAR', 'CELL', 'HOME', 'FAX', 'ISDN', 'MODEM', 'MSG', 'PAGER', 'PCS', 'VIDEO', 'WORK'),
					'visible'	=> true,
					'template'	=> '<span class="type [::lower::]">[::0::]</span>',
				),
			),
		),
	);

	/**
	 * Constructor
	 *
	 * @param array $cfg config
	 */
	public function __construct($cfg = array())
	{
		$this->debug = \bdk\Debug::getInstance();
		$this->cfg = ArrayUtil::mergeDeep($this->cfg, $cfg);
	}

	/**
	 * return a formatted date
	 *
	 * @param string|integer $date   accepts string or timestamp
	 * @param string         $format any PHP date format, or 'rel' - default = $this->cfg['date_format']
	 *
	 * @return string
	 */
	public function date($date, $format = null)
	{
		$ts = \is_int($date)
			? $date
			: \strtotime($date);
		if (empty($format)) {
			$format = $this->cfg['date_format'];
		}
		if ($format == 'rel') {
			$datetime = new DateTime();
			$date = $datetime->getRelTime($ts);
		} else {
			$date = date($format, $ts);
		}
		return $date;
	}

	/**
	 * return a formatted dollar ammount
	 *
	 * @param string|float $str dollar amount
	 *
	 * @return string
	 */
	public function dollar($str)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $str);
		$str = preg_replace('/[^\d.-]/', '', $str);
		$num = floatval($str);
		$str = '$'.number_format($num, 2);
		$this->debug->groupEnd();
		return $str;
	}

	/**
	 * Mask a string, such as an account or cc-num
	 *
	 * @param string  $string   string to mask
	 * @param boolean $repeat   (true) whether to repeat the mask char
	 * @param integer $numShow  (4) number of characters to leave unmasked
	 * @param string  $maskChar ('x')
	 *
	 * @return string
	 */
	public function mask($string, $repeat = true, $numShow = 4, $maskChar = 'x')
	{
		#$this->debug->log('func_start',__FUNCTION__);
		if (strlen($string) > $numShow) {
			$string = ( $repeat
					? str_repeat($maskChar, strlen($string)-$numShow)
					: $maskChar
				)
				.substr($string, -$numShow);
		}
		#$this->debug->groupEnd();
		return $string;
	}

	/**
	 * formats phone
	 *   depending on options passed may return
	 *		a formatted phone without any html markup
	 *		phone # wrapped with <span class="nowrap">
	 *		turned into a link <a class="a_tel nowrap" href="tel:+1555333444">(555) 333-444</a>
	 *		and/or with micoformat markup added
	 *    see http://en.wikipedia.org/wiki/E.123
	 *    see http://microformats.org/wiki/hcard
	 *
	 * @param string|array  $str  phone-number|options
	 * @param array|boolean $opts (array) return-markup (boolean)
	 *
	 * @return string
	 */
	public function tel($str, $opts = array())
	{
		$this->debug->groupCollapsed(__METHOD__, $str);
		if (is_array($str)) {
			$opts = $str;
			$str = null;
		} elseif (is_bool($opts)) {
			$opts = array(
				'markup' => $opts,
			);
		}
		$opts = array_merge($this->cfg['tel_opts'], $opts);
		// $this->debug->log('opts', $opts);
		$opts['notation'] = null;	// // 'international', 'national' or 'local'
		if ($opts['link'] === 'auto') {
			$net = new Net();
			$opts['link'] = $net->isMobileClient();
		}
		$strNew = $str;
		if (!empty($opts['display'])) {
			$this->debug->log('display passed');
			$strAlphaNum = preg_replace('/[^a-zA-Z0-9]/', '', $opts['display']);
		} else {
			$this->debug->log('determine display');
			/*
			if (empty($str) && !empty($opts['value'])) {
				$str = $opts['value'];
			}
			*/
			$opts['display'] = $str
				? $str
				: $opts['value'];
			$strAlphaNum = preg_replace('/[^a-zA-Z0-9]/', '', $opts['display']);
			if (preg_match('/^1?([a-zA-Z0-9]{10})$/', $strAlphaNum, $matches)) {
				// this appears to be a US/North-American phone #
				$opts['display'] = preg_match('/[a-z]/i', $opts['display'])
					? $opts['display']	// contains alpha... don't touch formatting
					: vsprintf('(%0s) %0s-%0s', sscanf($matches[1], '%3s%3s%4s'));
				$opts['notation'] = 'international';
				if (empty($opts['value'])) {
					// $opts['value'] = '+1 '.vsprintf('%0s %0s %0s',sscanf($matches[1],'%3s%3s%4s'));
					$opts['value'] = '+1'.$matches[1];
				}
			} elseif (preg_match('/\b\d{1,2}\b/', $opts['display'])) {		// international phone-#?
				$this->debug->log('international? -> not touching format');
				// $opts['display'] = $str;
				$opts['notation'] = 'international';
			} elseif (strlen($strAlphaNum) == 7) {
				$opts['display'] = vsprintf('%0s-%0s', sscanf($strAlphaNum, '%3s%4s'));
				$opts['notation'] = 'local';
			}
		}
		// $this->debug->log('opts', $opts);
		if ($opts['markup']) {
			$this->debug->log('adding .tel markup');
			$strNew = $this->telWithMarkup($opts);
		} else {
			$this->debug->log('no markup... just return display');
			$strNew = '{{display}}';
		}
		if ($opts['link']) {
			$opts['href'] = !empty($opts['value'])
				? $opts['link_schema'].':'.$opts['value']
				: $opts['link_schema'].':'.$opts['display'];
			$class = 'a_tel';
			if (!$opts['markup']) {
				$class .= ' nowrap';
			}
			$opts['class'] = $class;
			$opts['display'] = Str::quickTemp($opts['link_template'], $opts);
		}
		if ($opts['nowrap'] && !$opts['link'] && !$opts['markup']) {
			$strNew = '<span class="nowrap">[::display::]</span>';
		}
		#$this->debug->log('str_new',$strNew);
		$strNew = Str::quickTemp($strNew, $opts);
		$this->debug->log('str_new', $strNew);
		$this->debug->groupEnd();
		return $strNew;
	}

	private function telNumericVal($val)
	{
		if (!preg_match('/[a-z]/i', $val)) {
			return $val;
		}
		$chars = str_split(strtoupper($val));
		$val = '';
		foreach ($chars as $c) {
			if ($c >= 'A' && $c <= 'C') {
				$val .= '2';
			} elseif ($c >= 'D' && $c <= 'F') {
				$val .= '3';
			} elseif ($c >= 'G' && $c <= 'I') {
				$val .= '4';
			} elseif ($c >= 'J' && $c <= 'L') {
				$val .= '5';
			} elseif ($c >= 'M' && $c <= 'O') {
				$val .= '6';
			} elseif ($c >= 'P' && $c <= 'S') {
				$val .= '7';
			} elseif ($c >= 'T' && $c <= 'V') {
				$val .= '8';
			} elseif ($c >= 'W' && $c <= 'Z') {
				$val .= '9';
			} else {
				$val .= $c;
			}
		}
		return $val;
	}

	private function telWithMarkup(&$opts)
	{
		$opts['str_types'] = '';
		$typesAllHidden = true;
		if (!empty($opts['type'])) {
			$this->debug->log('type(s) supplied');
			$typeTrans = array(
				'CELL' 	=> array('MOBILE'),
				'WORK'	=> array('OFFICE'),
			);
			if (!is_array($opts['type'])) {
				$opts['type'] = array( $opts['type'] );
			}
			$opts['str_types'] = array(
				'visible'	=> array(),	// gather all the visible types
				'hidden'	=> array(),	// gather all the hidden types
			);
			foreach ($opts['type'] as $type) {
				$typeSearch = strtoupper($type);
				foreach ($typeTrans as $kUse => $a) {
					if (in_array($typeSearch, $a)) {
						$type = $kUse;
						break;
					}
				}
				foreach ($opts['type_templates'] as $a) {
					if (in_array($typeSearch, $a['types'])) {
						$str = Str::quickTemp($a['template'], array($type,'lower'=>'tel-'.strtolower($type)));
						if ($a['visible']) {
							$typesAllHidden = false;
							$opts['str_types']['visible'][] = $str;
						} else {
							$opts['str_types']['hidden'][] = $str;
						}
						break;
					}
				}
			}
			$opts['str_types'] = implode('', $opts['str_types']['hidden'])
								.implode($opts['type_separator'], $opts['str_types']['visible'])
								.$opts['type_end']
								.'';
		}
		if ($opts['notation'] == 'international' && $opts['value']) {
			// $opts['value'] = preg_replace('/[\(\)]/','',$opts['display']);			// remove parens
			// $opts['value'] = preg_replace('/[^+a-zA-Z0-9]/',' ',$opts['value']);		// convert non-alpha-numeric to space
			$opts['value'] = preg_replace('/[^+a-zA-Z0-9]/', '', $opts['value']);		// remove non-alpha-numeric
		}
		if ($opts['value'] || preg_match('/[a-z]/i', $opts['display'])) {
			$this->debug->log('have a value, or need one because of alpha chars');
			if (empty($opts['value'])) {
				// $opts['value'] = $strAlphaNum;
				$opts['value'] = $opts['display'];
			}
			$opts['value'] = $this->telNumericVal($opts['value']);
			if ($opts['value'] == $opts['display']) {
				$opts['value'] = null;
			}
		}
		// we now have str_types, display & value
		$attribs = array(
			'class'		=> 'tel',
		);
		$innerhtml = '{{str_types}}';
		if (!empty($opts['value'])) {
			$innerhtml .= '<span class="value" style="display:none;">{{value}}</span>';
		}
		$spanClass = '';
		if ($opts['str_types']) {
			if (empty($opts['value'])) {
				$spanClass = 'value';
			}
			if ($typesAllHidden) {
				$attribs['class'] .= ' nowrap';
			} else {
				$spanClass .= ' nowrap';
			}
		} else {
			$attribs['class'] .= ' nowrap';
		}
		$innerhtml .= !empty($spanClass) && !$opts['link']
			? '<span class="'.trim($spanClass).'">{{display}}</span>'
			: '{{display}}';
		// $this->debug->log('attribs', $attribs);
		// $this->debug->log('innerhtml', $innerhtml);
		return Html::buildTag('span', $attribs, $innerhtml);
	}
}
