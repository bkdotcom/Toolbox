<?php

use bdk\Net;

/**
 * PHPUnit tests for functions_common.php
 */
class NetTest extends \PHPUnit\Framework\TestCase
{

	/*
	public function test_email_build_body()
	{
	}
	*/

	/**
	 * @return void
	 */
	public function testIsIPin()
	{
	}

	/**
	 * @return void
	 */
	public function testIsIPlocal()
	{
	}

	/**
	 * @return void
	 */
	public function testIsMobileClient()
	{
	}

	/**
	 * @return void
	 *
	 * @todo more tests, incl attachments
	 */
	public function testEmail()
	{
		// mail(to, subject, body, additional_headers, additional_parameters)
		$to		= 'test@test.com';
		$from	= 'test@test.com';
		$sub	= 'This is a test';
		$body	= 'hello there';
		$email = new \bdk\Email(array(
			'email' => array(
				'from' => $from,
			),
			'debugMode'=>true,
		));
		$email->send(array(
			'to'		=> $to,
			'subject'	=> $sub,
			'body'		=> $body,
		));
		$this->assertSame(array(
			$to,
			$sub,
			$body,
			'From: test@test.com'."\r\n".'Content-Type: text/html',
			( $sendmailPath = ini_get('sendmail_path') ) && !strpos($sendmailPath, $from)
				? '-f '.$from
				: '',
		), $email->debugResult);
	}

	/**
	 * @return void
	 */
	public function testGetHeaderValue()
	{
	}
}
