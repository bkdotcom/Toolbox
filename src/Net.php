<?php

namespace bdk;

use bdk\Config;
use bdk\Email;

/**
 * Net Class
 */
class Net extends Config
{

	protected static $cfgStatic = array(
		'email' => array(
			'from' => '',
		),
		'debugMode' => false,
		'proxy' => null,	// not actually used by Net() class
								// if Base->cfg['Net']['proxy'] is set.. will be used as default for
								// FetchUrl() && Soap()
	);
	// private $debug = null;
	private static $isMobileClient = array(); // cache user agent strings
	// public $debugResult;

	/**
	 * A wrapper for PHP's mail function
	 *	$headers = array(
	 *		to				=> string or array,	// required
	 *		subject			=> string,			// required
	 *		body			=> string,			// required
	 *		From			=> string,
	 *		Reply-To		=> string,
	 *		cc				=> string or array,
	 *		bcc				=> string or array,
	 *		Content-Type	=> 'text/plain; charset="US-ASCII"'
	 *							'text/plain; charset=iso-8859-1'
	 *		X-Mailer		=> 'PHP/'.phpversion()
	 *		attachments		=> array()
	 *	)
	 *
	 * @param array $headers parts of the email (to, subject, body, etc)
	 *
	 * @return boolean
	 */
	/*
	public function email($headers = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$return = false;
		// $mime_boundary	= '';
		$eol		= "\r\n";
		$re_email = '[a-z0-9_]+([_\.-][a-z0-9]+)*@'
					.'([a-z0-9]+([\.-][a-z0-9]+)*)+'
					.'(\.[a-z]{2,})';
		$headers_add_str	= '';
		$params_add_str		= '';
		$headers_default = array(
			'To'		=> '',
			'Subject'	=> '',
			'Body'		=> '',
			'From'		=> $this->cfg['email']['from'],
		);
		$preg_callback = create_function('$m', 'return ucfirst($m[1]);');
		$arrayUtil = new ArrayUtil();
		foreach ($headers as $k => $v) {
			$k_new = preg_replace_callback('/(\w*)/', $preg_callback, strtolower(strtr($k, '_', '-')));
			$arrayUtil->keyRename($headers, $k, $k_new);
		}
		$headers = array_merge($headers_default, $headers);
		foreach ($headers as $k => $v) {
			if (in_array($k, array('To','Cc','Bcc'))) {
				// display-name <addr>
				// display-name chars a-z0-9!#$%&'*+-/=?^_`{|}~
				$addresses = is_string($v)
					? preg_split('|[;,]\s*|', preg_replace('/[\n\r]/', ' ', $v))	// split addresses on , or ;
					: $v;														// already array
				foreach ($addresses as $ka => $va) {
					if (!preg_match('#<'.$re_email.'>|^'.$re_email.'$#i', trim($va))) {
						unset($addresses[$ka]);
					} elseif (isset($_SERVER['WINDIR'])) {
						// PHP on Windows doesn't like the format 'john doe <jdoe@domain.com>'
						$addresses[$ka] = preg_match('/<(.*?)>/', $va, $matches)
							? $matches[1]
							: $va;
					}
				}
				$v = implode(', ', $addresses);
				$headers[$k] = $v;
			}
		}
		if (!empty($headers['Attachments'])) {
			$headers['MIME-Version'] = '1.0';
			if (!empty($headers['Body'])) {
				array_unshift($headers['Attachments'], array('body'=>$headers['Body']));
				if (!empty($headers['Content-Type'])) {
					$headers['Attachments'][0]['content_type'] = $headers['Content-Type'];
				}
			}
			list($headers['Content-Type'], $headers['Body']) = $this->emailBuildBody($headers['Attachments']);
			unset($headers['Attachments']);
		}
		// build headers_add_str
		foreach ($headers as $k => $v) {
			if (in_array($k, array('To','Subject','Body','Attachments'))) {
				continue;
			}
			if ($k == 'From') {
				$from_address = preg_match('/<(.*?)>/', $v, $matches)
					? $matches[1]
					: $v;
				if (isset($_SERVER['WINDIR'])) {
					$this->debug->log('setting sendmail_from to '.$from_address);
					ini_set('sendmail_from', $from_address);
				} elseif (( $sendmail_path = ini_get('sendmail_path') ) && !strpos($sendmail_path, $from_address)) {
					$params_add_str = '-f '.$from_address;
					$this->debug->log('sendmail_path', $sendmail_path.' '.$params_add_str);
				}
			}
			$headers_add_str .= $k.': '.$v.$eol;
		}
		$headers_add_str = substr($headers_add_str, 0, -strlen($eol));	// remove final $eol;
		if ($this->debug->getCfg('collect')) {
			$str_debug = '';
			foreach ($headers as $k => $v) {
				if (in_array($k, array('Attachments', 'Body'))) {
					continue;
				}
				$str_debug .= '<u>'.$k.'</u>: '.htmlspecialchars($v).$eol;
			}
			$str_debug .= $eol.htmlspecialchars(
				preg_replace('/(Content-Transfer-Encoding:\s+base64'.$eol.$eol.').+'.$eol.$eol.'/s', '$1removed for debug output'.$eol.$eol, $headers['Body'])
			);
			$this->debug->log(
				'Email:<br />'
				.'<pre style="margin-left:2em; padding:.25em; border:#999 solid 1px; background-color:#DDD; color:#000;">'
				.$str_debug
				.'</pre>'
			);
		}
		$params = array($headers['To'], $headers['Subject'], $headers['Body'], $headers_add_str, $params_add_str);
		if (ini_get('safe_mode')) {
			if (!empty($params_add_str)) {
				$this->debug->info('safe mode... removing additional parameters param', $params_add_str);
			}
			array_pop($params);
		}
		if ($headers['To']) {
			if ($this->cfg['debug_mode']) {
				$this->debug->warn('debug_mode: email not sent');
				// don't sent email when debug=true
				$return = true;
				$this->debugResult = $params;	// @todo : what uses this?
			} else {
				$return = call_user_func_array('mail', $params);
			}
		}
		$this->debug->log('Email was '.(($return)?'':'NOT').' successful');
		$this->debug->groupEnd();
		return $return;
	}
	*/
	/**
	 * Build email body
	 *
	 * @param array $attachments an array detailing the type of attachment
	 *
	 * @return array
	 * @internal
	 */
	/*
	protected function emailBuildBody($attachments = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		#$this->debug->log('attachments',$attachments);
		$body = '';
		$eol = "\r\n";
		$mime_boundary = '==Multipart_Boundary_x'.substr(md5(uniqid(rand(), true)), 0, 10).'x';
		$multipart_type = '';
			// 'multipart/mixed';
			// 'multipart/alternative';
			// 'multipart/related';
		$content_types = array();
		$self_called = Php::getCallerInfo(false) == __CLASS__.'::'.__FUNCTION__;
		$this->debug->log('self_called', $self_called);
		for ($k=0; $k<count($attachments); $k++) {
			$a = $attachments[$k];
			$a_keys = array_keys($a);
			if ($k==0 && is_string($a)) {
				$multipart_type = $a;
				continue;
			} elseif ($a_keys[0] == '0') {
				$this->debug->log('new array');
				list($a, $b) = $this->emailBuildBody($a);
				$body .= '--'.$mime_boundary.$eol;
				$body .= 'Content-Type: '.$a.$eol.$eol;
				$body .= $b;
				continue;
			}
			$a_defaults = array(
				'content_type'	=> 'text/plain',	// 'application/octet-stream',
				'disposition'	=> 'attachment',	// inline
				'body'			=> '',
				'file'			=> null,
				'filename'		=> null,
				'content_id'	=> null,
			);
			$a = array_merge($a_defaults, $a);
			#$this->debug->log('a', $a);
			if (isset($a['file'])) {
				$a['body'] = file_get_contents($a['file']);
				if (empty($a['filename'])) {
					$a['filename'] = basename($a['file']);
				}
				$a['content_transfer_encoding'] = 'base64';
				$a['body'] = chunk_split(base64_encode($a['body']), 68, $eol);
				$a['body'] = substr($a['body'], 0, -strlen($eol));	// remove final $eol
				$a['file'] = null;
			}
			// .'Content-Type: text/plain; charset="ISO-8859-1"'.$eol
			// .'Content-Transfer-Encoding: 7bit'.$eol
			if (empty($multipart_type)) {
				if ($a['content_type'] == 'text/html') {
					$this->debug->log('html');
					$multipart_type = 'multipart/alternative';
					if (strpos($a['body'], 'cid:')) {
						$this->debug->log('found "cid:"');
						if (in_array('text/plain', $content_types)) {
							$this->debug->log('prior alternative');
							$attachments[$k] = $a;
							$attachments[] = array_splice($attachments, $k);
							$k--;
							continue;
						} else {
							$multipart_type = 'multipart/related';
						}
					}
				}
			}
			if (!in_array($a['content_type'], $content_types)) {
				$content_types[] = $a['content_type'];
			}
			$body .= '--'.$mime_boundary.$eol;
			$body .= 'Content-Type: '.$a['content_type'].';'.( !empty($a['filename']) ? ' name="'.$a['filename'].'";' : '' ).$eol;	// charset="ISO-8859-1";
			if (!empty($a['filename'])) {
				$body .= 'Content-Disposition: '.$a['disposition'].'; filename="'.$a['filename'].'"'.$eol;
			}
			if (!empty($a['content_id'])) {
				$body .= 'Content-ID: <'.$a['content_id'].'>'.$eol;
			}
			if (!empty($a['content_transfer_encoding'])) {
				$body .= 'Content-Transfer-Encoding: '.$a['content_transfer_encoding'].$eol;
			}
			$body .= $eol;
			$body .= $a['body'].$eol.$eol;
		}
		$body .= '--'.$mime_boundary.'--'.$eol;
		#$this->debug->log('content_types', $content_types);
		if (!$self_called) {
			$body = ''
				.'This is a multi-part message in MIME format.'.$eol
				.$eol
				.$body;
		}
		if (empty($multipart_type)) {
			$multipart_type = 'multipart/mixed';
		}
		$multipart_type = $multipart_type.'; boundary="'.$mime_boundary.'";';
		$this->debug->groupEnd();
		return array($multipart_type, $body);
	}
	*/

	/**
	 * Email
	 *
	 * @param array $headers headers
	 *
	 * @return boolean
	 */
	public static function email($headers = array())
	{
		\bdk\Debug::getInstance()->setErrorCaller();
		$new = 'Email->send()';
		trigger_error('deprecated:  '.__FUNCTION__.'... use '.$new.' instead', E_USER_DEPRECATED);
		$email = new Email(self::$cfgStatic);
		$return = $email->send($headers);
		return $return;
	}

	/**
	 * Get header value
	 *
	 * @param string       $key     Defaults to 'Content-Type'
	 * @param array|string $headers =array() Defaults to sent headers, can be string, numeric array, or key/value array
	 *
	 * @return string
	 */
	public static function getHeaderValue($key = 'Content-Type', $headers = array())
	{
		$value = null;
		if (empty($headers)) {
			// no headers passed... determine from sent
			if (function_exists('headers_list')) {
				$headers = headers_list();
			} elseif (function_exists('apache_response_headers')) {
				// apache_response_headers is somewhat worthless.... must flush the output first via ob_end_flush
				$headers = apache_response_headers();
				if (!headers_sent()) {
					// $this->debug->warn('headers not yet sent.. unable to determine complete list');
					// $this->debug->log('headers', $headers);
				}
			} else {
				// $this->debug->warn('unable to determine sent headers');
			}
		}
		if ($headers) {
			if (is_string($headers)) {
				$headers = explode("\n", $headers);
			}
			$arrayUtil = new ArrayUtil();
			if ($arrayUtil->isHash($headers)) {
				if (isset($headers[$key])) {
					$value = $headers[$key];
				}
			} else {
				foreach ($headers as $header) {
					if (preg_match('/^'.$key.':\s*([^;]*)/i', $header, $matches)) {
						$value = $matches[1];
						break;
					}
				}
			}
		}
		if (is_null($value) && $key == 'Content-Type') {
			$value = ini_get('default_mimetype');
		}
		// $this->debug->log('value', $value);
		// $this->debug->groupEnd();
		return $value;
	}

	/**
	 * is IP address in given subnet?
	 *
	 * @param string $ipAddr IP address
	 * @param string $net    network
	 * @param string $mask   netmask
	 *
	 * @return boolean
	 */
	public static function isIpIn($ipAddr, $net, $mask)
	{
		// doesn't check for the return value of ip2long
		$ipAddr	= ip2long($ipAddr);
		$rede	= ip2long($net);
		$mask	= ip2long($mask);
		$res	= $ipAddr & $mask;	// AND
		return ($res == $rede);
	}

	/**
	 * Returns whether or not IP address is on a local network
	 *
	 * @param string $ipAddr IP address
	 *
	 * @return boolean
	 */
	public static function isIpLocal($ipAddr)
	{
		// $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $ip);
		$return = false;
		if ($ipAddr == '::1') {
			$ipAddr = '127.0.0.1';
		}
		$net_n_masks = array(
			array('127.0.0.0',		'255.0.0.0'),
			array('10.0.0.0',		'255.0.0.0'),
			array('192.168.0.0',	'255.255.0.0'),
			array('172.16.0.0',		'255.240.0.0'),
			array('169.254.0.0',	'255.255.0.0'),
		);
		foreach ($net_n_masks as $nma) {
			if (self::isIpIn($ipAddr, $nma[0], $nma[1])) {
				$return = true;
				break;
			}
		}
		// $this->debug->groupEnd();
		return $return;
	}

	/**
	 * Is client a mobile device?
	 *
	 * @param string $ua optional userAgent string
	 *
	 * @return boolean
	 * @link http://www.zytrax.com/tech/web/mobile_ids.html
	 * @link http://www.andymoore.info/php-to-detect-mobile-phones/
	 * @version 2.0
	 */
	public static function isMobileClient($ua = null)
	{
		// $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$return = false;
		$httpAccept = null;
		if (empty($ua)) {
			$ua = $_SERVER['HTTP_USER_AGENT'];
			if (isset($_SERVER['HTTP_ACCEPT'])) {
				$httpAccept = $_SERVER['HTTP_ACCEPT'];
			}
		}
		if (isset(self::$isMobileClient[$ua])) {
			// $this->debug->groupEnd();
			return self::$isMobileClient[$ua];
		}
		// check if the user agent gives away any tell tale signs it's a mobile browser
		if (preg_match('/Android(?:\s(\d+\.\d+))?/', $ua)) {
			// Android! - impossible to determine phone vs tablet with any reliability!
			if (preg_match('/\b(Nexus)\b/', $ua)) {
				$return = false;		// it's a tablet
			} elseif (preg_match('/\bMobile\b/i', $ua)) {
				$return = true;			// it's a phone
			}
		} elseif (preg_match('/up.browser|up.link|windows ce|iemobile|mini|mmp|symbian|midp|wap|phone|pocket|mobile|\bpda\b|psp/i', $ua)) {
			$return = true;
		} elseif (stristr($httpAccept, 'text/vnd.wap.wml') || stristr($httpAccept, 'application/vnd.wap.xhtml+xml')) {
			// check the http accept header to see if wap.wml or wap.xhtml support is claimed
			$return = true;
		} elseif (isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE']) || isset($_SERVER['X-OperaMini-Features']) || isset($_SERVER['UA-pixels'])) {
			// check if there are any tell tales signs it's a mobile device from the _server headers
			$return = true;
		} else {
			// the first four characters from the most common mobile user agents
			$agents = array('acs-','alav','alca','amoi','audi','aste','benq','bird','blac','blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno','ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-','maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-','newt','noki','opwv','palm','pana','pant','pdxg','phil','play','pluc','port','prox','qtek','qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar','sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-','tosh','tsm-','upg1','upsi','vk-v','voda','w3c ','wap-','wapa','wapi','wapp','wapr','webc','winw','winw','xda','xda-');
			$a2 = array('avantgo');
			foreach ($a2 as $a2_str) {
				$agents[] = substr($a2_str, 0, 4);
			}
			$parts = explode('; ', strtolower($ua));
			foreach ($parts as $part) {
				if (in_array(substr($part, 0, 4), $agents)) {
					$return = true;
					foreach ($a2 as $a2_str) {
						if (( substr($part, 0, 4) == substr($a2_str, 0, 4) )
							&& ( strpos($part, $a2_str) === false )
						) {
							// matches 1st four of a2_str, but not whole
							$return = false;
						}
					}
					if ($return) {
						break;
					}
				}
			}
		}
		if ($return && preg_match('/\biPad\b/', $ua)) {
			$return = false;
		}
		self::$isMobileClient[$ua] = $return;
		// $this->debug->groupEnd();
		return $return;
	}

	/**
	 * Parse header string
	 *
	 * @param string $headerString header block
	 *
	 * @return array
	 */
	public static function parseHeaders($headerString)
	{
		$headers = explode("\n", $headerString);
		$keys = array_keys($headers);
		$regexReturnCode = '/^\S+\s+(\d+)\s?(.*)/';
		$regexHeader = '/^(.*?):\s*(.*)$/';
		$headers['Return-Code'] = preg_match($regexReturnCode, $headerString, $matches)
			? $matches[1]
			: 200;
		foreach ($keys as $k) {
			$v = trim($headers[$k]);
			unset($headers[$k]);
			if (preg_match($regexHeader, $v, $matches)) {
				$k = $matches[1];
				$k = preg_replace_callback('/(^|-)(.?)/', function ($matches) {
					return $matches[1].strtoupper($matches[2]);
				}, $k);
				$v = trim($matches[2]);
				if (isset($headers[$k])) {
					if (!is_array($headers[$k])) {
						$headers[$k] = array($headers[$k]);
					}
					$headers[$k][] = $v;
				} else {
					$headers[$k] = $v;
				}
			} elseif (preg_match($regexReturnCode, $v, $matches)) {
				$headers['Return-Code'] = $matches[1];
			}
		}
		return $headers;
	}
}
