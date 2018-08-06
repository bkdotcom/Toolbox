<?php

use bdk\Html;
use bdk\Str;

/**
 * PHPUnit tests for functions_common.php
 */
class StringTests extends \PHPUnit\Framework\TestCase
{

	/**
	 * @return void
	 */
	public function testQuickTemp()
	{
		$testStack = array(
			array(
				'params' => array('[::foo bar::][::razmataz::][::test::]\[::test::][::a/2::]',array(
					'foo bar' 	=> 'blah',
					'test'		=> 'billy',
					'a'	=> array(
						2 => 'two',
					),
				)),
				'expect' => 'blahbilly[::test::]two',
			),
			array(
				'params' => array('[::0::][::1::]',array(
					'foo',
					'bar',
				)),
				'expect' => 'foobar',
			),
			array(
				'params' => array('[::0::][::1::]',
					'blah',
					'billy'
				),
				'expect' => 'blahbilly',
			),
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Str::quickTemp', $test['params']);
			$this->assertEquals($test['expect'], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testIsUtf8()
	{
	}

	/**
	 * @return void
	 */
	public function testRemoveAccents()
	{
		$testStack = array(
			array(
				'params' => array('ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖÙÚÛÜÝßàáâãäåçèéêëìíîïðñòóôõöùúûüýÿ'),
				'expect' => 'AAAAAACEEEEIIIIDNOOOOOUUUUYBaaaaaaceeeeiiiionooooouuuuyy',
			),
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Str::removeAccents', $test['params']);
			$this->assertEquals($test['expect'], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testPlural()
	{
		$testStack = array(
			array(
				'params' => array(0,'thing'),
				'expect' => 'things',
			),
			array(
				'params' => array(1,'thing'),
				'expect' => 'thing',
			),
			array(
				'params' => array(2,'thing'),
				'expect' => 'things',
			),
			array(
				'params' => array(0,'spy','spies'),
				'expect' => 'spies',
			),
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Str::plural', $test['params']);
			$this->assertEquals($test['expect'], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testIsBase64Encoded()
	{
	}

	/**
	 * @return void
	 */
	public function testIsBinary()
	{
	}

	/**
	 * @return void
	 */
	public function testToUtf8()
	{
		$testStack = array(
			array(
				'str' => 'plain',
				'expect' => 'plain',
			),
			array(
				'str' => base64_decode('SHVmZm1hbi1HaWxyZWF0aCBKZW7p'),
				'expect' => 'Huffman-Gilreath Jen&eacute;',
			),
			array(
				'str' => base64_decode('SmVu6SBIdWZmbWFuLUdpbHJlYXRoIGFzc3VtZWQgaGVyIHJvbGUgYXMgdGhlIFByb2Zlc3Npb25hbCAmIEV4ZWN1dGl2ZSBPZmZpY2VyIGluIEF1Z3VzdCAyMDA3LiBKZW7pIGpvaW5lZCBBcnZlc3QgaW4gMjAwMiBhbmQgaGFzIGdhaW5lZCBhIHdlYWx0aCBvZiBrbm93bGVkZ2UgaW4gbWFueSBiYW5raW5nIGFyZWFzLCBzdWNoIGFzIGxlbmRpbmcsIHdlYWx0aCBtYW5hZ2VtZW50LCBhbmQgY29ycG9yYXRlIHNlcnZpY2VzLiBTaGUgaG9sZHMgYSBNYXN0ZXImcnNxdW87cyBkZWdyZWUgaW4gUHVibGljIEFkbWluaXN0cmF0aW9uIGFzIHdlbGwgYXMgYSBCU0JBIGluIFNtYWxsIEJ1c2luZXNzIEVudHJlcHJlbmV1cnNoaXAuIEplbukgaXMgdmVyeSBpbnZvbHZlZCBpbiB0aGUgY29tbXVuaXR5IGFuZCBzZXJ2ZXMgYXMgYSBtZW1iZXIgb2YgbnVtZXJvdXMgY2hhcml0YWJsZSBmb3VuZGF0aW9ucywgc3VjaCBhcyBEaWFtb25kcyBEZW5pbXMgYW5kIERpY2UgKFJlYnVpbGRpbmcgVG9nZXRoZXIpIGFuZCB0aGUgQWZyaWNhbiBFZHVjYXRpb24gUmVzb3VyY2UgQ2VudGVyLg=='),
				'expect' => 'Jen&eacute; Huffman-Gilreath assumed her role as the Professional &amp; Executive Officer in August 2007. Jen&eacute; joined Arvest in 2002 and has gained a wealth of knowledge in many banking areas, such as lending, wealth management, and corporate services. She holds a Master&rsquo;s degree in Public Administration as well as a BSBA in Small Business Entrepreneurship. Jen&eacute; is very involved in the community and serves as a member of numerous charitable foundations, such as Diamonds Denims and Dice (Rebuilding Together) and the African Education Resource Center.',
			),
			array(
				'str' => base64_decode('xZLFk8WgxaHFuMuGy5zigJrGkuKAnuKApuKAoOKAoeKAmOKAmeKAnOKAneKAouKAk+KAlOKEouKAsOKAueKAug=='),
				'expect' => '&OElig;&oelig;&Scaron;&scaron;&Yuml;&circ;&tilde;&sbquo;&fnof;&bdquo;&hellip;&dagger;&Dagger;&lsquo;&rsquo;&ldquo;&rdquo;&bull;&ndash;&mdash;&trade;&permil;&lsaquo;&rsaquo;',
			),
			array(
				'str' => base64_decode('jJyKmp+ImIKDhIWGh5GSk5SVlpeZiYub'),
				'expect' => '&OElig;&oelig;&Scaron;&scaron;&Yuml;&circ;&tilde;&sbquo;&fnof;&bdquo;&hellip;&dagger;&Dagger;&lsquo;&rsquo;&ldquo;&rdquo;&bull;&ndash;&mdash;&trade;&permil;&lsaquo;&rsaquo;',
			),
			array(
				'str' => base64_decode('k2ZhbmN5IHF1b3Rlc5Q='),
				'expect' => '&ldquo;fancy quotes&rdquo;',
			),
			array(
				'str' => base64_decode('4oCcZmFuY3kgcXVvdGVz4oCd'),
				'expect' => '&ldquo;fancy quotes&rdquo;',
			),
			array(
				'str' => '<b>'.chr(153).'</b>',
				'expect' => '<b>&trade;</b>',
			),
			array(
				'str' => 'bèfore <:: whoa ::> àfter',
				'expect' => 'b&egrave;fore <:: whoa ::> &agrave;fter',
			),
		);
		foreach ($testStack as $test) {
			$ret = Str::toUtf8($test['str']);
			$this->assertEquals($test['expect'], Html::htmlentities($ret, false));
		}
	}

	/**
	 * @return void
	 */
	public function testOrdinal()
	{
		$testStack = array(
			array( 1,  '1st' ),
			array( 2,  '2nd' ),
			array( 3,  '3rd' ),
			array( 4,  '4th' ),
			array( 5,  '5th' ),
			array( 11, '11th' ),
			array( 12, '12th' ),
			array( 13, '13th' ),
		);
		foreach ($testStack as $test) {
			$ret = Str::ordinal($test[0]);
			$this->assertEquals($test[1], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testGetBytes()
	{
		$testStack = array(
			array(
				'params' => array(1),
				'expect' => '1 B',
			),
			array(
				'params' => array(1024+500),
				'expect' => '1.49 kB',
			),
			array(
				'params' => array(1048576),
				'expect' => '1 MB',
			),
			array(
				'params' => array(1073741824),
				'expect' => '1 GB',
			),
			array(
				'params' => array('1MB',true),
				'expect' => '1048576',
			),
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Str::getBytes', $test['params']);
			$this->assertEquals($test['expect'], $ret);
		}
	}
}
