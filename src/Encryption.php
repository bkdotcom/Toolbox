<?php

namespace bdk;

use bdk\Str;

/**
 * Encryption helper
 */
class Encryption
{

	protected static $uksortMatchList;
	protected static $opensslKeyResources = array();
	protected static $paddingMethods = array(
		1 => array(
			'aliases'	=> array('PKCS5','PKCS7','RFC3852','RFC3369'),
			'desc'		=> 'Pad with bytes all of the same value as the number of padding bytes'
		),
		2 => array(
			'aliases'	=> array('RANK','BCMO','FERG','NIST800-38A'),
			'desc'		=> 'Pad with 0x80 followed by zero (null) bytes'
		),
		3 => array(
			'aliases'	=> array('SCHN'),
			'desc'		=> 'Pad with zeroes except make the last byte equal to the number of padding bytes'
		),
		4 => array(
			'aliases'	=> array('NULL'),	// php pads with nulls by default
			'desc'		=> 'Pad with zero (null) characters'
		),
		5 => array(
			'aliases'	=> array('SPACE','NZEDI'),
			'desc'		=> 'Pad with Spaces',
		),
	);

	/**
	 * A wrapper for encrypting/decrypting with mcrypt
	 * will detect if crypt-text is base64 encoded
	 * will detect if key is base64 encoded
	 * encryption returns base64_encoded by default
	 *
	 * Padding info - http://www.di-mgt.com.au/cryptopad.html
	 *
	 * @param string $action  'encrypt', 'decrypt', or 'keygen
	 * @param string $key     may be base64 encoded.. use mcrypt('keygen') to generate a key
	 * @param string $string  clear-text or crypt-text
	 * @param array  $options options
	 *
	 * @return string
	 */
	public static function mcrypt($action, $key = '', $string = '', $options = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $action);
		$debug->time();
		if (function_exists('mcrypt_encrypt')) {
			// mcrypt installed
			$options = self::mcryptOptions($options);
		} else {
			trigger_error('mcrypt not installed', E_USER_ERROR);
			$action = '';
			$string = false;
		}
		if ($action == 'encrypt') {
			$key = self::mcryptKey($key, $options);
			$string = self::mcryptEncrypt($key, $string, $options);
		} elseif ($action == 'decrypt') {
			$key = self::mcryptKey($key, $options);
			$string = self::mcryptDecrypt($key, $string, $options);
		} elseif ($action == 'keygen') {
			$debug->log('key_size', $options['key_size']);
			$string = '';
			while (strlen($string) < $options['key_size']) {
				$string .= sha1(uniqid(mt_rand(), true), true);
			}
			$string = base64_encode(substr($string, 0, $options['key_size']));
		}
		$debug->timeEnd('%time sec to '.$action);
		$debug->groupEnd();
		return $string;
	}

	/**
	 * get mcrypt options
	 *
	 * @param array $options passed  options
	 *
	 * @return array
	 */
	protected static function mcryptOptions($options)
	{
		$options_default = array(
			'cipher'	=> MCRYPT_RIJNDAEL_128,
			'mode'		=> MCRYPT_MODE_CBC,
			// encryption options
			'return_base64' => true,		// return cryptText base64_encoded
			'rand_src'	=> MCRYPT_RAND,
			'padding'	=> 'PKCS5',			// only applies with MCRYPT_MODE_CBC, see $padding_methods for values
			'iv'		=> null,			// usually randomly generated
			'iv_return'	=> true,			// can only be disabled when passing iv
		);
		$options = array_merge($options_default, $options);
		$options['iv_size']= mcrypt_get_iv_size($options['cipher'], $options['mode']);
		$options['key_size'] = mcrypt_get_key_size($options['cipher'], $options['mode']);
		$options['block_size'] = mcrypt_get_block_size($options['cipher'], $options['mode']);
		#$debug->log('iv_size', $options['iv_size']);
		#$debug->log('key_size', $options['key_size']);
		#$debug->log('block_size', $options['block_size']);
		return $options;
	}

	/**
	 * base64 decodes key
	 * hashes ascii key
	 * keeps key size <= key_size
	 *
	 * @param string $key     key
	 * @param array  $options options
	 *
	 * @return string
	 */
	protected static function mcryptKey($key, $options)
	{
		$debug = \bdk\Debug::getInstance();
		if (Str::isBase64Encoded($key) && ( strlen($key) >= $options['key_size'] * 1.333 && strlen($key) <= $options['key_size'] * 1.5)) {
			$debug->log('base64 decoding key');
			$key = base64_decode($key);
		}
		$ascii = true;
		for ($i=0; $i<strlen($key); $i++) {
			if (0x80 & ord($key{$i})) {
				$ascii = false;
				break;
			}
		}
		if ($ascii) {
			$debug->log('hashing ascii key');
			$new_key	= '';
			$num_segs	= ceil($options['key_size']/20);		// 20 is length of raw sha1 output
			$seg_length	= ceil(strlen($key)/$num_segs);
			for ($i=0; $i<$num_segs; $i++) {
				$substr = substr($key, $i*$seg_length, $seg_length);
				$new_key .= sha1($substr, true);
			}
			$key = $new_key;
		}
		if (strlen($key) > $options['key_size']) {
			$debug->log('sizing key');
			$key = substr($key, 0, $options['key_size']);
		}
		return $key;
	}

	/**
	 * Encrypt string
	 *
	 * @param string $key     key
	 * @param string $string  string to encrypt
	 * @param array  $options options
	 *
	 * @return string
	 */
	protected static function mcryptEncrypt($key, $string, $options)
	{
		if ($options['rand_src'] == MCRYPT_RAND) {
			srand();
		}
		$iv	= $options['iv']
			? $options['iv']
			: mcrypt_create_iv($options['iv_size'], $options['rand_src']);
		$options['iv_return'] = !($options['iv'] && !$options['iv_return']);
		if ($options['padding'] && $options['mode'] == MCRYPT_MODE_CBC) {
			$options['padding'] = strtoupper($options['padding']);
			foreach (self::$paddingMethods as $k => $a) {
				if (in_array($options['padding'], $a['aliases'])) {
					#$debug->log('padding method '.$k.' ('.$options['padding'].')', $a['desc']);
					$pad_length = $options['block_size'] - strlen($string) % $options['block_size'];
					if ($k == 1) {
						$padding = str_repeat(chr($pad_length), $pad_length);
					} elseif ($k == 2) {
						$padding = chr(0x80).str_repeat(chr(0), $pad_length-1);
					} elseif ($k == 3) {
						$padding = str_repeat(chr(0), $pad_length-1).chr($pad_length);
					} elseif ($k == 4) {
						$padding = str_repeat(chr(0), $pad_length);
					} elseif ($k == 5 && $pad_length != $options['block_size']) {
						$padding = str_repeat(' ', $pad_length);
					}
					$string .= $padding;
					break;
				}
			}
		}
		$string	= ( $options['iv_return'] ? $iv : '' )
			.mcrypt_encrypt($options['cipher'], $key, $string, $options['mode'], $iv);
		if ($options['return_base64']) {
			#$debug->log('base64 encoding crypt text');
			$string = base64_encode($string);
		}
		return $string;
	}

	/**
	 * Decrypt string
	 *
	 * @param string $key     key
	 * @param string $string  string to decrypt
	 * @param array  $options options
	 *
	 * @return string
	 */
	protected static function mcryptDecrypt($key, $string, $options)
	{
		$debug = \bdk\Debug::getInstance();
		if (Str::isBase64Encoded($string)) {
			$debug->log('base64 decoding crypt text');
			$string = base64_decode($string);
		}
		$iv		= substr($string, 0, $options['iv_size']);
		$string	= substr($string, $options['iv_size']);
		if ($string && strlen($iv) == $options['iv_size']) {
			$string	= mcrypt_decrypt($options['cipher'], $key, $string, $options['mode'], $iv);
		}
		// now remove any padding
		$last_chr = ord(substr($string, -1));
		#$debug->log('last_chr', $last_chr);
		$method_used = 0;
		if ($last_chr > 0 && $last_chr <= $options['block_size']) {
			$remove = substr($string, -$last_chr);
			if ($remove == str_repeat(chr($last_chr), $last_chr)) {
				$method_used = 1;
			} elseif ($remove == str_repeat(chr(0), $last_chr-1).chr($last_chr)) {
				$method_used = 3;
			}
			if ($method_used) {
				$string = substr($string, 0, -$last_chr);
			}
		} elseif ($last_chr == 0 && preg_match('/\x00{1,'.$options['block_size'].'}$/', $string, $matches)) {
			$remove = $matches[0];
			$length = strlen($remove);
			$string = substr($string, 0, -$length);
			$method_used = 4;
			if ($length < $options['block_size'] && ord(substr($string, -1)) == 0x80) {
				$method_used = 2;
				$remove = chr(0x80).$remove;
				$string = substr($string, 0, -1);
			}
		}
		if ($method_used) {
			$debug->log('padding removed ('.trim(chunk_split(bin2hex($remove), 2, ' ')).'), method', $method_used);
		}
		return $string;
	}

	/**
	 * OpenSSL
	 *
	 * @param string $action one of the following
	 *		keygen		generates a new private-key/public-key
	 *						if $opts['dn'] is passed a csr is generated along with a self-signed cert
	 *						returns an associative array
	 *		encrypt		by default expects a public key
	 *		decrypt		by default expects a private key
	 *						will detect if data is base64_encoded
	 *		seal		expects a public key (or an array of public keys)
	 *						returns an assoc array with 'data' & 'ekeys'
	 *		open		expects a private key
	 *						pass data and encrypted key as an array to data
	 *						array('data'=>encrypted_data, 'key'=>encrypted_key)
	 *						will detect if data and/or key is base64_encoded
	 *		sign		expects a private key
	 *						returns signature
	 *		verify		expects a public key
	 *						pass data and signature as an array to data
	 *						array('data'=>data, 'signature'=>signature)
	 *		freekeys
	 *
	 * @param mixed  $key    key
	 * @param string $data   data
	 * @param array  $opts   options
	 *
	 * @return mixed
	 */
	public static function openssl($action, $key = null, $data = null, $opts = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $action);
		$opts = array_merge(array(
			'pop' => in_array($action, array('encrypt','seal','verify'))
				? 'public'
				: 'private',
			'return_base64' => true,
			'days'			=> 365,		// used when self-signing cert
			'configargs'	=> array(),
			'signature_alg' => OPENSSL_ALGO_SHA1,
		), $opts);
		#$debug->log('opts', $opts);
		$return = false;
		if ($key) {
			if (!is_array($key)) {
				// go ahead and make key an array, as 'seal' can take an array
				$key = array($key);
			}
			$key_ks_all		= array_keys($key);	//  openssl_sign doesn't preseve keys
			$key_ks_valid	= $key_ks_all;
			foreach ($key_ks_all as $i => $key_k) {
				$k = $key[$key_k];
				if (!is_resource($k)) {
					$md5 = $opts['pop'].':'.md5($k);
					if (isset(self::$opensslKeyResources[$md5])) {
						$debug->log('cached key resource');
						$k = self::$opensslKeyResources[$md5];
					} else {
						$debug->log('k', $k);
						if (is_file($k)) {
							$k = file_get_contents($k);
						}
						$k = $opts['pop'] == 'public'
							? openssl_pkey_get_public($k)
							: openssl_pkey_get_private($k);
						if ($k) {
							self::$opensslKeyResources[$md5] = $k;
						} else {
							$debug->warn('error getting '.$opts['pop'].' key '.$key_k);
						}
					}
					if ($k) {
						$key[$key_k] = $k;
					} else {
						unset($key[$key_k]);		// so not passed to openssl_seal()
						unset($key_ks_valid[$i]);
					}
				}
			}
			if ($action != 'seal') {
				$key = array_pop($key);
			}
			$debug->log('key', $key);
		}
		if ($action == 'keygen') {
			$return = array(
				'csr' => null,
				'crt' => null,
				'pub' => null,
				'pri' => null,
			);
			$pkey = openssl_pkey_new($opts['configargs']);											// returns key resource
			$debug->log('pkey', $pkey);
			if (!empty($opts['dn'])) {
				/*
				create a csr & self-signed-cert
				*/
				$csr = openssl_csr_new($opts['dn'], $pkey, $opts['configargs']);							// returns x.509 CSR resource
				$selfSignedCert = openssl_csr_sign($csr, null, $pkey, $opts['days'], $opts['configargs']);	// returns x.509 cert resource
				// $debug->log('selfSignedCert', $selfSignedCert);
				if (is_resource($csr)) {
					openssl_csr_export($csr, $return['csr']);
					openssl_x509_export($selfSignedCert, $return['crt']);										// pem format
				}
			} else {
				/*
				create a public key
				*/
				$pubkey = openssl_pkey_get_details($pkey);
				$return['pub'] = $pubkey['key'];
			}
			/*
			now genereate private key
			*/
			openssl_pkey_export($pkey, $return['pri'], null, $opts['configargs']);
		} elseif ($action == 'encrypt') {
			if ($opts['pop'] == 'public') {
				openssl_public_encrypt($data, $return, $key);
			} else {
				openssl_private_encrypt($data, $return, $key);
			}
		} elseif ($action == 'decrypt') {
			if (\bdk\Str::isBase64Encoded($data)) {
				$data = base64_decode($data);
			}
			if ($opts['pop'] == 'public') {
				openssl_public_decrypt($data, $return, $key);
			} else {
				openssl_private_decrypt($data, $return, $key);
			}
		} elseif ($action == 'seal') {
			openssl_seal($data, $return, $ekeys, $key);				// key should be an array of public keys
			$ekeys = array_combine($key_ks_valid, $ekeys);
			if (count($key_ks_all) != count($key_ks_valid)) {
				foreach ($key_ks_all as $k) {
					if (!isset($ekeys[$k])) {
						$ekeys[$k] = false;
					}
				}
				self::uksortMatch($ekeys, $key_ks_all);
			}
			$return = array(
				'data'	=> $return,
				'keys'	=> $ekeys,
			);
		} elseif ($action == 'open') {
			foreach (array('data','key') as $k) {
				if (\bdk\Str::isBase64Encoded($data[$k])) {
					$data[$k] = base64_decode($data[$k]);
				}
			}
			openssl_open($data['data'], $return, $data['key'], $key);	// key is private key
		} elseif ($action == 'sign') {
			openssl_sign($data, $return, $key, $opts['signature_alg']);									// private key
		} elseif ($action == 'verify') {
			if (\bdk\Str::isBase64Encoded($data['signature'])) {
				$data['signature'] = base64_decode($data['signature']);
			}
			$return = openssl_verify($data['data'], $data['signature'], $key, $opts['signature_alg']);	// public key
		} elseif ($action == 'freekeys') {
			$return = true;
			foreach (self::$opensslKeyResources as $k => $r) {
				openssl_pkey_free($r);
				unset(self::$opensslKeyResources[$k]);
			}
		}
		while (($e = openssl_error_string()) !== false) {
			if (strpos($e, 'NCONF_get_string:no value')) {
				continue;
			}
			$debug->error($e);
		}
		if ($opts['return_base64'] && in_array($action, array('encrypt','seal','sign'))) {
			if ($action == 'seal') {
				$return['data'] = trim(chunk_split(base64_encode($return['data'])));
				$return['keys'] = array_map('base64_encode', $return['keys']);
			} else {
				$return = trim(chunk_split(base64_encode($return)));
			}
		}
		$debug->groupEnd();
		return $return;
	}

	/**
	 * sort an array so that the keys match the order of the keys $keys
	 *
	 * @param array $array to sort by keys
	 * @param array $keys  the order by which the keys should be sorted
	 *
	 * @return void
	 */
	protected static function uksortMatch(&$array, $keys)
	{
		self::$uksortMatchList = $keys;
		uksort($array, array($this, 'uksortmatchFunc'));
		self::$uksortMatchList = array();
	}

	/**
	 * [uksortMatchFunc description]
	 *
	 * @param string $a key a
	 * @param string $b key b
	 *
	 * @return integer
	 */
	protected static function uksortMatchFunc($a, $b)
	{
		$posa = array_search($a, self::$uksortMatchList);
		$posb = array_search($b, self::$uksortMatchList);
		if ($posa == $posb) {
			return 0;
		} else {
			return $posa > $posb ? 1 : -1;
		}
	}

	/**
	 * R-MD5
	 * This algorithm is basically a two-level XOR.
	 *
	 * The encrypt function XORs the plaintext with a random number.
	 * This random number is embedded in the output (to save it) by alterating its chars with the XOR output.
	 * This is passed to rmd5_keyed(), which XORs it with our text key.
	 *
	 * Decryption is the reverse and works on the principal that
	 * x XOR y XOR y = x.
	 *
	 * @param string $action 'encrypt' or 'decrypt'
	 * @param string $key    key
	 * @param string $str    input to either encrypt or decrypt
	 *
	 * @return string
	 */
	public static function rmd5($action, $key, $str)
	{
		$ret = '';
		$ctr = 0;
		if ($action == 'encrypt') {
			srand((double)microtime()*1000000);
			$salt = md5(rand(0, 32000));
			for ($i=0; $i<strlen($str); $i++) {
				if ($ctr == strlen($salt)) {
					$ctr = 0;
				}
				$ret .= substr($salt, $ctr, 1).( substr($str, $i, 1)^substr($salt, $ctr, 1) );
				$ctr++;
			}
			$ret = self::rmd5('keyed', $key, $ret);
			$ret = base64_encode($ret);
		} elseif ($action == 'decrypt') {
			$str = base64_decode($str);
			$str = self::rmd5('keyed', $key, $str);
			for ($i=0; $i<strlen($str); $i+=2) {
				$ret .= substr($str, $i, 1)^substr($str, $i+1, 1);
			}
		} elseif ($action == 'keyed') {
			$key = md5($key);
			for ($i=0; $i<strlen($str); $i++) {
				if ($ctr == strlen($key)) {
					$ctr = 0;
				}
				$ret .= substr($str, $i, 1)^substr($key, $ctr, 1);
				$ctr++;
			}
		}
		return $ret;
	}
}
