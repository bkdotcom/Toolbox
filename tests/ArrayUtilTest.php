<?php

use bdk\ArrayUtil;
use bdk\FileIo;

/**
 * PHPUnit tests for functions_common.php
 */
class ArrayTest extends \PHPUnit\Framework\TestCase
{

	/**
	 * @return array
	 */
	public function arrayProvider()
	{
		$filepath = __DIR__.'/test.csv';
		$rows = FileIo::readDelimFile($filepath, 'key');
		return array(
			array($rows),
		);
	}


	/**
	 * @return void
	 */
	public function testArrayRemove()
	{
	}

	/**
	 * @return void
	 */
	public function testArrayMapDeep()
	{
	}

	/**
	 * @return void
	 */
	public function testPath()
	{
		$array = array(
			'1a' => 'string',
				// there is no 1b
			'1c' => array(	// empty
			),
			'1d' =>array(
				'1d2a' => '2a',
				'1d2b' => array(),
				'1d2c' => array('a','b'),
			),
		);
		$this->assertEquals($array['1d']['1d2a'], ArrayUtil::path($array, '/1d/__reset__'));
		$this->assertEquals($array['1d']['1d2c'], ArrayUtil::path($array, '/1d/__end__'));
		$this->assertEquals($array['1d']['1d2a'], ArrayUtil::path($array, '/1d/1d2a'));
		$this->assertEquals($array['1d']['1d2c'], ArrayUtil::path($array, '/1d/1d2c'));
		ArrayUtil::path($array, '/1b/1b2a', 'foo');
		$this->assertEquals('foo', $array['1b']['1b2a']);
		$newVal = array('1b2a3a'=>'1b2a3a','1b2a3b'=>'1b2a3b');
		$was = ArrayUtil::path($array, '/1b/1b2a', $newVal);
		$this->assertEquals($newVal, $array['1b']['1b2a']);
		$this->assertEquals('foo', $was);
	}

	/**
	 * @return void
	 */
	public function testIsHash()
	{
		$this->assertFalse(ArrayUtil::isHash(array('foo','bar')));
		$this->assertFalse(ArrayUtil::isHash(array(0=>'foo',2=>'bar')));
		$this->assertFalse(ArrayUtil::isHash(array()));
		$this->assertTrue(ArrayUtil::isHash(array('foo'=>'bar')));
	}

	/**
	 * @return void
	 */
	public function testColumnKeys()
	{
		$array = array(
			'a' => array( 'a' => 'a',	'b' => 'b',	),
			'b' => array( 'a' => 'a',	'b' => 'b',	),
			'b' => array( 'c' => 'c',	'b' => 'b',	),
		);
		$this->assertEquals(array('c','a','b'), ArrayUtil::columnKeys($array));
	}

	/**
	 * @return void
	 */
	public function testKeyRename()
	{
	}

	/**
	 * @return void
	 */
	public function testKeyArraySplice()
	{
	}

	/**
	 * @return void
	 */
	public function testArrayMergeReplace()
	{
	}

	/**
	 * @return void
	 */
	public function testImplodeDelim()
	{
	}

	/**
	 * @param array $array returned from test_read_delim_file()
	 *
	 * @return void
	 * @dataProvider arrayProvider
	 */
	public function testSearchFields($array)
	{
		// test non-exact matches
		$found = ArrayUtil::searchFields($array, array('col1'=>'oo'));
		$this->assertEquals('foo', $found['k5']['col1']);
		$found = ArrayUtil::searchFields($array, array('col1'=>'oo','col2'=>'A'));
		$this->assertEquals('foo', $found['k5']['col1']);
		$this->assertEquals('bar', $found['k5']['col2']);
		// test exact match
		$found = ArrayUtil::searchFields($array, array('col1'=>'joe','col2'=>'bob'), true);
		$this->assertEquals('joe', $found['k6']['col1']);
		$this->assertEquals('bob', $found['k6']['col2']);
		// test no match found
		$this->assertSame(array(), ArrayUtil::searchFields($array, array('col1'=>'oo'), true));
	}

	/**
	 * @param array $array returned from test_read_delim_file()
	 *
	 * @return void
	 * @dataProvider arrayProvider
	 */
	public function testFieldSort($array)
	{
		$array = ArrayUtil::fieldSort($array, array('col1','col2'));
		$this->assertEquals(array('k4','k2','k3','k1','k5','k6'), array_keys($array));
		$array = ArrayUtil::fieldSort($array, array('col1','DESC','col2'));
		$this->assertEquals(array('k6','k5','k1','k3','k4','k2'), array_keys($array));
	}

	/**
	 * @param array $rows returned from test_read_delim_file()
	 *
	 * @return void
	 * @dataProvider arrayProvider
	 */
	public function testUniqueCol($rows)
	{
		$array = ArrayUtil::uniqueCol($rows, 'col1');
		$this->assertCount(5, $array);
		$this->assertTrue(ArrayUtil::isHash($array[0]));
		$array = ArrayUtil::uniqueCol($rows, 'col1', false);
		$this->assertCount(5, $array);
		$this->assertFalse(ArrayUtil::isHash($array[0]));
	}
}
