<?php

use bdk\FileIo;

/**
 * PHPUnit tests for functions_common.php
 */
class FileIoTest extends \PHPUnit\Framework\TestCase
{

	/**
	 * @return void
	 */
	public function testReadDelimFile()
	{
		$filepath = __DIR__.'/test.csv';
		$rows = FileIo::readDelimFile($filepath, 'key');
		$keys = array_keys($rows['k1']);
		$this->assertEquals(array('col1','col2','z','z 2','key','a space'), $keys);
	}

	/**
	 * @return void
	 */
	public function testSafeFopen()
	{
	}

	/**
	 * @return void
	 */
	public function testSafeFclose()
	{
	}

	/**
	 * @return void
	 */
	public function testWriteDelimFile()
	{
	}

	/**
	 * @return void
	 */
	public function testSafeFileWrite()
	{
	}

	/**
	 * @return void
	 */
	public function testSafeFileAppend()
	{
	}



}
