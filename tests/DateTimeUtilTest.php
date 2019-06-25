<?php

/**
 * format_data tests
 */
class DateTimeUtilTest extends \PHPUnit\Framework\TestCase
{

	/**
	 * @return void
	 */
	public function testDatetimeToTs()
	{
		$testStack = array(
			array(
				'params' => array(new \DateTime('2014-11-07 12:45:00')),
				'expect' => 1415385900,
			),
			array(
				'params' => array(new \DateTimeImmutable('2014-11-07 12:45:00')),
				'expect' => 1415385900,
			),
			array(
				'params' => array('2014-11-07 12:45:00'),
				'expect' => 1415385900,
			),
			array(
				'params' => array(1415385900),
				'expect' => 1415385900,
			),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::datetimeToTs',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	public function testGetDate()
	{
		$testStack = array(
			array(
				'params' => array(new \DateTime('2014-11-07 12:45:00')),
				'expect' => array(
					'seconds'	=> '00',
					'minutes'	=> '45',
					'hours'		=> '12',
					'mday'		=> 7,
					'wday'		=> 7,
					'mon'		=> 11,
					'year'		=> '2014',
					'yday'		=> 310,
					'weekday'	=> 'Friday',
					'month'		=> 'November',
					'0'			=> 1415385900,
				),
			),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::getDate',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	public function testGetDateTime()
	{
		$testStack = array(
			array(
				'params' => array(new \DateTime('2014-11-07 12:45:00')),
				'expect' => new \DateTime('2014-11-07 12:45:00'),
			),
			array(
				'params' => array('2014-11-07 12:45:00'),
				'expect' => new \DateTime('2014-11-07 12:45:00'),
			),
			array(
				'params' => array(1415385900),
				'expect' => new \DateTime('2014-11-07 12:45:00'),
			),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::getDateTime',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	public function testGetNextBusinessDay()
	{
		$testStack = array(
			array(
				'params' => array('2014-11-07 12:45:00'),
				'expect' => new DateTime('2014-11-10 00:00:00'),
			),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::getNextBusinessDay',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	public function testGetRange()
	{
		$testStack = array(
            array(
                'params' => array('2014-11-07 12:45:00', array('format'=>'Y-m-d H:i:s')),
                'expect' => array(
                    'passed' => '2014-11-07 12:45:00',
                    'start' => '2014-11-07 00:00:00',
                    'end' => '2014-11-07 23:59:59',
                    'prev' => '2014-11-06 00:00:00',
                    'next' => '2014-11-08 00:00:00',
                    'minus' => '2014-11-06 12:45:00',
                    'plus' => '2014-11-08 12:45:00',
                ),
            ),
            array(
                'params' => array('2014-11-07 12:45:00', array(
                    'range' => 'week',
                    'format'=>'Y-m-d H:i:s',
                )),
                'expect' => array(
                    'passed' => '2014-11-07 12:45:00',
                    'start' => '2014-11-02 00:00:00',
                    'end' => '2014-11-08 23:59:59',
                    'prev' => '2014-10-26 00:00:00',
                    'next' => '2014-11-09 00:00:00',
                    'minus' => '2014-10-31 12:45:00',
                    'plus' => '2014-11-14 12:45:00',
                ),
            ),
            array(
                'params' => array('2014-11-07 12:45:00', array(
                    'range' => 'week',
                    'format'=>'D Y-m-d H:i:s',
                    'weekStartDay' => 1,
                )),
                'expect' => array(
                    'passed' => 'Fri 2014-11-07 12:45:00',
                    'start' => 'Mon 2014-11-03 00:00:00',
                    'end' => 'Sun 2014-11-09 23:59:59',
                    'prev' => 'Mon 2014-10-27 00:00:00',
                    'next' => 'Mon 2014-11-10 00:00:00',
                    'minus' => 'Fri 2014-10-31 12:45:00',
                    'plus' => 'Fri 2014-11-14 12:45:00',
                ),
            ),
            array(
                'params' => array('2014-11-16 12:45:00', array(
                    'range' => 'week',
                    'format'=>'D Y-m-d H:i:s',
                    'weekStartDay' => 1,
                )),
                'expect' => array(
                    'passed' => 'Sun 2014-11-16 12:45:00',
                    'start' => 'Mon 2014-11-10 00:00:00',
                    'end' => 'Sun 2014-11-16 23:59:59',
                    'prev' => 'Mon 2014-11-03 00:00:00',
                    'next' => 'Mon 2014-11-17 00:00:00',
                    'minus' => 'Sun 2014-11-09 12:45:00',
                    'plus' => 'Sun 2014-11-23 12:45:00',
                ),
            ),
            array(
                'params' => array('2018-01-31 12:34:56', array(
                    'range' => 'month',
                    'format'=>'D Y-m-d H:i:s',
                )),
                'expect' => array(
                    'passed' => 'Wed 2018-01-31 12:34:56',
                    'start' => 'Mon 2018-01-01 00:00:00',
                    'end' => 'Wed 2018-01-31 23:59:59',
                    'prev' => 'Fri 2017-12-01 00:00:00',
                    'next' => 'Thu 2018-02-01 00:00:00',
                    'minus' => 'Sun 2017-12-31 12:34:56',
                    'plus' => 'Wed 2018-02-28 12:34:56',
                ),
            ),
            array(
                'params' => array('2018-03-30 12:34:56', array(
                    'range' => 'month',
                    'format'=>'Y-m-d H:i:s',
                )),
                'expect' => array(
                    'passed' => '2018-03-30 12:34:56',
                    'start' => '2018-03-01 00:00:00',
                    'end' => '2018-03-31 23:59:59',
                    'prev' => '2018-02-01 00:00:00',
                    'next' => '2018-04-01 00:00:00',
                    'minus' => '2018-02-28 12:34:56',
                    'plus' => '2018-04-30 12:34:56',
                ),
            ),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::getRange',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	public function testGetRelTime()
	{
		$testStack = array(
            array(
                // future (same day)
                'params' => array(
                    '2018-09-25 14:30:00',
                    '2018-09-25 11:00:00',
                ),
                'expect' => 'Today at 2:30pm',
            ),
            array(
                // future (tomorrow)
                'params' => array(
                    '2018-09-26',
                    '2018-09-25',
                ),
                'expect' => 'Tomorrow',
            ),
            array(
                // future (tomorrow with h:m)
                'params' => array(
                    '2018-09-26 12:12:12',
                    '2018-09-25',
                ),
                'expect' => 'Tomorrow at 12:12pm',
            ),
            array(
                // future
                'params' => array(
                    '2018-09-29 12:12:12',
                    '2018-09-25',
                ),
                'expect' => 'September 29th',
            ),
            array(
                'params' => array('-30 sec'),
                'expect' => 'Just now',
            ),
            array(
                'params' => array('-'.(60*5 + 11).' sec'),
                'expect' => '5 min ago',
            ),
            array(
                'params' => array('-'.(3600*2 + 60*3 + 42).' sec'),
                'expect' => '2 hours ago',
            ),
            array(
                // more than six hours ago, but still today
                'params' => array(
                    new \DateTime('2018-01-01 05:59:00'),
                    new \DateTime('2018-01-01 12:00:00')
                ),
                'expect' => 'Today at 5:59am',
            ),
            array(
                // yesterday @ 11:15pm
                'params' => array((new \DateTime('yesterday'))->setTime(23, 15)),
                'expect' => 'Yesterday at 11:15pm',
            ),
            array(
                // yesterday @ 12:00am
                'params' => array(new \DateTime('yesterday')),
                'expect' => 'Yesterday',
            ),
            array(
                // within last week
                'params' => array(
                    new \DateTime('2017-12-26 12:00:00'),
                    new \DateTime('2018-01-01 05:59:00')
                ),
                'expect' => 'Tuesday at 12:00pm',
            ),
            array(
                // earlier in year
                'params' => array(
                    new \DateTime(\date('Y').'-02-15 00:00:00'),
                    new \DateTime(\date('Y').'-07-04 21:15:00')
                ),
                'expect' => 'February 15th',
            ),
            array(
                // prev year
                'params' => array(
                    new \DateTime('2017-12-22 12:00:00'),
                    new \DateTime('2018-01-01 05:59:00')
                ),
                'expect' => 'December 22nd, 2017',
            ),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::getRelTime',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	public function testMktime()
	{
		$testStack = array(
            array(
                'params' => array(12, 34, 56, '12', '06', '1973'),
                'expect' => new \DateTime('1973-12-06 12:34:56'),
            ),
            array(
                'params' => array(-1, -1, -1, 0, '06', '1974'),
                'expect' => new \DateTime('1973-12-05 22:58:59'),
            ),
            array(
                'params' => array(-1, -1, -1, 0, 0, '1985'),
                'expect' => new \DateTime('1984-11-29 22:58:59'),
            ),
            array(
                'params' => array(12, 34, 56, 12, 06, 10),
                'expect' => new \DateTime('0010-12-06 12:34:56'),
            ),
            array(
                'params' => array(12, 34, 56, 12, 06, '10'),
                'expect' => new \DateTime('2010-12-06 12:34:56'),
            ),
            array(
                'params' => array(12, 34, 56, 12, 06, '85'),
                'expect' => new \DateTime('1985-12-06 12:34:56'),
            ),
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::mktime',
				$test['params']
			));
		}
	}

	/**
	 * @return void
	 */
	/*
    public function testStrtotime()
	{
		$testStack = array(
		);
		foreach ($testStack as $test) {
			$this->assertEquals($test['expect'], call_user_func_array(
				'\\bdk\\DateTimeUtil::strtotime',
				$test['params']
			));
		}
	}
    */
}
