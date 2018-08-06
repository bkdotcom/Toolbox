<?php

use bdk\Encryption;

class EncryptionTest extends \PHPUnit\Framework\TestCase
{

	/**
	 * @requires extension mcrypt
	 */
	public function testMcrypt()
	{
		// loop through several combinations of ciphers modes & padding
		// to simply make sure that encrypting -> decrypting returns the original text
		$ciphers	= array(MCRYPT_3DES,MCRYPT_RIJNDAEL_128,MCRYPT_RIJNDAEL_256,MCRYPT_BLOWFISH,MCRYPT_TWOFISH);
		$modes		= array(MCRYPT_MODE_ECB,MCRYPT_MODE_CBC,MCRYPT_MODE_CFB,MCRYPT_MODE_OFB,MCRYPT_MODE_NOFB);
		$pads		= array('PKCS5','RANK','SCHN','NULL',null);
		$strings	= array( 'Hello there', '
1 This is super secret
2 This is super secret
3 This is super secret
4 This is super secret
5 This is super secret
6 This is super secret
7 This is super secret
8 This is super secret
9 This is super secret
',
		);
		foreach ($ciphers as $cipher) {
			foreach ($modes as $mode) {
				$key =
				$opts = array(
					'cipher'	=> $cipher,
					'mode'		=> $mode,
					// 'padding'	=> $pad,
				);
				$key = Encryption::mcrypt('keygen', null, null, $opts);
				#debug('key',$key);
				foreach ($pads as $pad) {
					$opts['padding'] = $pad;
					foreach ($strings as $cleartext_start) {
						// $db_was = debug('collect', false);
						$crypttext = Encryption::mcrypt('encrypt', $key, $cleartext_start, $opts);
						#debug('info','crypttext',$crypttext);
						$cleartext = Encryption::mcrypt('decrypt', $key, $crypttext, $opts);
						// debug('collect', $db_was);
						#debug('info','cleartext',$cleartext);
						$this->assertEquals($cleartext_start, $cleartext);
					}
				}
			}
		}
		$testStack = array(
			array(
				'params' => array(
					'decrypt',
					hex2bin('06a9214036b8a15b512e03d534120006'),
					hex2bin('3dafba429d9eb430b422da802c9fac41e353779c1079aeb82708942dbe77181a'),
				),
				'expect' => 'Single block msg',
			),
			array(
				'params' => array(
					'decrypt',
					hex2bin('0123456789ABCDEFF0E1D2C3B4A59687'),
					base64_encode(hex2bin('6B77B4D63006DEE605B156E27403979358DEB9E7154616D959F1652BD5FF92CC')),
					array('cipher'=>MCRYPT_BLOWFISH),
				),
				'expect' => 'Now is the time for ',

			),
			array(
				'params' => array(
					'decrypt',
					base64_decode('GVE/3J2k+3KkoF62aRdUjTyQ/5TVQZ4fI2PuqJ3+4d0='),
					base64_decode('6tDgdp8TeASbIn/GdzHl5OaQMTcXBn0E0mU0bI+nDt8dottDZl66OtQJ/bk37ESSdfDODEUQ2IKIitKHyRnK3A=='),
					array(
						'iv' => base64_decode('bXFHX2aGvgBNauSArXGqQw=='),
					),
				),
				'expect' => 'om|1247579554|192.168.1.100|appID|1234|foo|bar',
			),
		);
		foreach ($testStack as $test) {
			$return = call_user_func_array('\bdk\Encryption::mcrypt', $test['params']);
			$this->assertEquals($test['expect'], $return);
		}
	}

	/**
	 * @requires extension openssl
	 */
	public function testOpenssl()
	{
	}

	public function testRmd5()
	{
		$key = 'opensesame';
		$cleartext = 'super secret string';
		$crypttext = Encryption::rmd5('encrypt', $key, $cleartext);
		// echo 'crypttext = '.$crypttext;
		$this->assertEquals($cleartext, Encryption::rmd5('decrypt', $key, $crypttext));
		$crypttext = 'BCQFdwErDjBUdlR2CXIANAFiUHkAZwB4UX9XJQVyWiwAOgNqXWA=';
		$this->assertEquals($cleartext, Encryption::rmd5('decrypt', $key, $crypttext));
	}
}
