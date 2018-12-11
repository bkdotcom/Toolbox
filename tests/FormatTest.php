<?php

use bdk\Format;

/**
 * format_data tests
 */
class FormatTest extends \PHPUnit\Framework\TestCase
{

	/**
	 * @return void
	 */
	public function testFormatDate()
	{
		$testStack = array(
			array(
				'params' => array('20120718'),
				'expect' => '2012-07-18',
			),
			array(
				'params' => array(1342587600,'F jS, Y'),
				'expect' => 'July 18th, 2012',
			),
		);
		$format = new Format();
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(array($format, 'date'), $test['params']));
		}
	}

	/**
	 * @return void
	 */
	public function testFormatDollar()
	{
		$testStack = array(
			array(
				'params' => array(12345.678),
				'expect' => '$12,345.68',
			),
		);
		$format = new Format();
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(array($format, 'dollar'), $test['params']));
		}
	}

	/**
	 * @return void
	 */
	public function testFormatTel()
	{

		$testStack = array(
			array(
				'params' => array('14798569971', 	array() ),
				'expect' => '<span class="nowrap">(479) 856-9971</span>',
			),
			array(
				'params' => array('14798569971', 	array('nowrap'=>false) ),
				'expect' => '(479) 856-9971',
			),
			array(
				'params' => array('14798569971', 	array('link'=>true) ),
				'expect' => '<a class="a_tel nowrap" href="tel:+14798569971">(479) 856-9971</a>',
			),
			array(
				'params' => array('14798569971', 	array('markup'=>true) ),
				'expect' => '<span class="nowrap tel"><span class="value" style="display:none;">+14798569971</span>(479) 856-9971</span>',
			),
			array(
				'params' => array('856-9971',		array() ),
				'expect' => '<span class="nowrap">856-9971</span>',
			),
			array(
				'params' => array('+61 7 3289 7645',array() ),
				'expect' => '<span class="nowrap">+61 7 3289 7645</span>',
			),
			array(
				'params' => array('1800BIGDORK',	array('type' => 'cell', 'markup'=>true) ),
				'expect' => '<span class="tel"><span class="type tel-cell">cell</span>: <span class="value" style="display:none;">+18002443675</span><span class="nowrap">1800BIGDORK</span></span>',
			),
			array(
				'params' => array('1800BIGDORK',	array( 'type' => 'FAX', 'markup'=>true) ),
				'expect' => '<span class="tel"><span class="type tel-fax">FAX</span>: <span class="value" style="display:none;">+18002443675</span><span class="nowrap">1800BIGDORK</span></span>',
			),
			array(
				'params' => array('+61 (7) 3289 7645',array( 'type' => array('home','pref'), 'markup'=>true) ),
				'expect' => '<span class="tel"><span class="type" style="display:none;">pref</span><span class="type tel-home">home</span>: <span class="value nowrap">+61 (7) 3289 7645</span></span>',
			),
			array(
				'params' => array('(7) 3289 7645',	array() ),
				'expect' => '<span class="nowrap">(7) 3289 7645</span>',
			),
		);
		$format = new Format();
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(array($format, 'tel'), $test['params']));
		}
	}
}
