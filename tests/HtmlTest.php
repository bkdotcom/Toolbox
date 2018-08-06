<?php

use bdk\Html;

/**
 * PHPUnit tests for functions_common.php
 */
class HtmlTests extends \PHPUnit\Framework\TestCase
{

	/**
	 * @return void
	 */
	public function testBuildUrl()
	{
		$param_stack = array(
			array(
				'url'		=> 'http://www.test.com/some/path?foo=bar#anchor',
				'expect'	=> 'http://www.test.com/some/path?foo=bar#anchor',
			),
			array(
				'host'		=> 'www.test.com',
				'expect'	=> 'http://www.test.com/',
			),
			array(
				'host'		=> 'www.test.com',
				'scheme'	=> 'omit',
				'expect'	=> '//www.test.com/',
			),
			array(
				'url'		=> '//www.testy.com',
				'expect'	=> '//www.testy.com/',
			),
			array(
				'url'		=> 'https://www.test.com/path/was.php?foo=bar#snap',
				'path'		=> '/new.php',
				'expect'	=> 'https://www.test.com/new.php',
			),
			array(
				'url'		=> 'https://www.test.com/path/was.php?foo=bar#snap',
				'query'		=> 'up=down&down=low',
				'params'	=> array('up'=>'high'),
				'expect'	=> 'https://www.test.com/path/was.php?foo=bar&up=high&down=low#snap',
			),
			array(
				'path'		=> '/test/path.php',
				'query'		=> 'up=down&down=low',
				'params'	=> array('up'=>'high'),
				'expect'	=> '/test/path.php?up=high&down=low',
			),
			array(
				'query'		=> 'up=high',
				'expect'	=> '/?up=high',
			),
			array(
				'params'	=> array('up'=>'high'),
				'expect'	=> '/?up=high',
			),
			array(
				// no slash on path
				//  ? on query
				'scheme' => 'http',
				'host'	=> 'www.test.com',
				'port'	=> '8080',
				'user'	=> 'user',
				'pass'	=> 'pass',
				'path'	=> 'some/path',
				'query'	=> '?foo=bar&up=down',
				'params'=> array( 'up' => 'high', 'down' => 'low' ),
				'anchor' => 'test',
				'expect' => 'http://user:pass@www.test.com:8080/some/path?foo=bar&up=high&down=low',
			),
		);
		foreach ($param_stack as $i => $test) {
			$url = Html::buildUrl($test);
			$this->assertEquals($test['expect'], $url, 'Data set '.($i+1));
		}
	}

	/**
	 * @return void
	 */
	public function testBuildQueryString()
	{
		$testStack = array(
			array(
				'params' => array(array('foo'=>'bar','joe'=>'bob','null'=>null)),
				'expect' => 'foo=bar&joe=bob',
			),
			array(
				'params' => array(array('foo'=>'"bar & baz"')),
				'expect' => 'foo=%22bar+%26+baz%22',
			),
			array(
				'params' => array(array('foo'=>array('a','b'=>'be'))),
				'expect' => 'foo[0]=a&foo[b]=be',
			),
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Html::buildQueryString', $test['params']);
			$this->assertEquals($test['expect'], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testBuildAttribString()
	{
		$testStack = array(
			array(
				'params' => array(array(
					'placeholder'=>'"quotes" & ampersand!',
					'required'=>true,
					'autofocus'
				)),
				'expect' => ' autofocus="autofocus" placeholder="&quot;quotes&quot; &amp; ampersand!" required="required"',
			),
			/*
			array(
				'params' => array(array('cname'=>'a','alt'=>'"quotes" & ampersand!'),true),
				'expect' => '<a alt="&quot;quotes&quot; &amp; ampersand!"></a>',
			),
			array(
				// note that innerhtml is NOT special char'd
				'params' => array(array('cname'=>'a','innerhtml'=>'"quotes" & ampersand!','title'=>'"quotes" & ampersand!'),true),
				'expect' => '<a title="&quot;quotes&quot; &amp; ampersand!">"quotes" & ampersand!</a>',
			),
			array(
				// note that attribs are double encoded, innerhtml is not
				'params' => array(array('cname'=>'a','innerhtml'=>'"quotes&quot; &amp; ampersand!','title'=>'"quotes&quot; &amp; ampersand!'),true,true),
				'expect' => '<a title="&quot;quotes&amp;quot; &amp;amp; ampersand!">"quotes&quot; &amp; ampersand!</a>',
			),
			*/
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Html::buildAttribString', $test['params']);
			// echo "\n".$ret;
			$this->assertEquals($test['expect'], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testClassAdd()
	{
		$testStack = array(
			array(
				// tag
				'params' => array(
					'<div class="classname">blah</div>',
					array('foo','bar'),
				),
				'expect' => '<div class="bar classname foo">blah</div>',
			),
			array(
				// attrib string
				'params' => array(
					'foo="bar"',
					array('foo','bar'),
				),
				'expect' => ' class="bar foo" foo="bar"',
			),
			array(
				// class value
				'params' => array(
					'classname',
					array('foo','bar'),
				),
				'expect' => 'bar classname foo',
			),
			array(
				// attribs array
				'params' => array(
					array('data-test'=>true),
					array('foo','bar'),
				),
				'expect' => array('class'=>'bar foo', 'data-test'=>true),
			),
			array(
				// classnames array
				'params' => array(
					array('classname'),
					array('foo','bar'),
				),
				'expect' => array('bar','classname','foo'),
			),
		);
		foreach ($testStack as $i => $test) {
			// hack for passing by reference
			$keys = array_keys($test['params']);
			foreach ($keys as $k) {
				$test['params'][$k] = &$test['params'][$k];
			}
			$ret = call_user_func_array('\bdk\Html::classAdd', $test['params']);
			$this->assertEquals($test['expect'], $ret, 'Data set '.($i+1));
		}
	}

	public function testClassRemove()
	{
		$testStack = array(
			array(
				// tag
				'params' => array(
					'<div class="classname foo bar">blah</div>',
					array('foo','bar'),
				),
				'expect' => '<div class="classname">blah</div>',
			),
			array(
				// attribs array
				'params' => array(
					array('class'=>'classname foo bar'),
					array('foo','bar'),
				),
				'expect' => array('class'=>'classname'),
			),
			array(
				// classnames array
				'params' => array(
					array('foo','classname','bar'),
					array('foo','bar'),
				),
				'expect' => array('classname'),
			),
		);

		foreach ($testStack as $i => $test) {
			// hack for passing by reference
			$keys = array_keys($test['params']);
			foreach ($keys as $k) {
				$test['params'][$k] = &$test['params'][$k];
			}
			$ret = call_user_func_array('\bdk\Html::classRemove', $test['params']);
			$this->assertEquals($test['expect'], $ret, 'Data set '.($i+1));
		}
	}

	/**
	 * @return void
	 * @todo   tests for options: keep_params, fullUrl, & chars
	 */
	public function testGetSelfUrl()
	{
		/*
		$_SERVER['REQUEST_URI'] = '/some/path/?page=test&foo=bar';
		$parts = parse_url($_SERVER['REQUEST_URI']);
		$_SERVER['QUERY_STRING'] = $parts['query'];
		parse_str($_SERVER['QUERY_STRING'], $_GET);
		$_GET['page'] = 'some new page';
		$this->assertEquals('/some/path/?page=some+new+page&amp;foo=bar', Html::getSelfUrl());
		*/
		// modify $_GET[page]... but lock value to REQUEST_URI
		$_SERVER['REQUEST_URI'] = '/some/path/?foo=bar';
		$parts = parse_url($_SERVER['REQUEST_URI']);
		$_SERVER['QUERY_STRING'] = $parts['query'];
		parse_str($_SERVER['QUERY_STRING'], $_GET);
		$_GET['page'] = 'some new page';
		// $GLOBALS['common_cfg']['self_link']['get_lock'][] = 'page';
		\bdk\Config::setCfg('Html/selfUrl/getLock', array('page'));
		$this->assertEquals('/some/path/?foo=bar', Html::getSelfUrl());
		/*
		// a mod-rewrite type situation:
		// page param will not be included
		$_SERVER['REQUEST_URI'] = '/some/path/?foo=bar';
		$_SERVER['QUERY_STRING'] = 'page=some/path&foo=bar';
		parse_str($_SERVER['QUERY_STRING'], $_GET);
		$this->assertEquals('/some/path/?foo=bar', Html::getSelfUrl());
		*/
	}

	/**
	 * @return void
	 */
	public function testParseAttribString()
	{
		$testStack = array(
			array(
				'params' => array(''),
				'expect' => array(),
			),
			array(
				// no quotes around autocomplete falue
				// required = required
				// blah
				'params' => array(' placeholder="&quot;quotes&quot; &amp; ampersands" autocomplete=off required=required blah'),
				'expect' => array(
					'autocomplete' => 'off',
					'blah' => true,
					'placeholder' => '"quotes" & ampersands',
					'required' => true,
				),
			),
			/*
			array(
				'params' => array('<div class="test" >stuff &amp; things</div>'),
				'expect' => array('cname'=>'div','class'=>'test','innerhtml'=>'stuff & things'),
			),
			*/
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Html::parseAttribString', $test['params']);
			$this->assertSame($test['expect'], $ret);
		}
	}

	/**
	 * @return void
	 */
	public function testHtmlentities()
	{
		$testStack = array(
			array(
				'params' => array('<div>'.chr(134).'</div>',false),
				'expect' => '<div>&dagger;</div>',
			),
			array(
				'params' => array('<div>&dagger;</div>',false),
				'expect' => '<div>&dagger;</div>',
			),
		);
		foreach ($testStack as $test) {
			$ret = call_user_func_array('\bdk\Html::htmlentities', $test['params']);
			$this->assertEquals($test['expect'], $ret);
		}
	}
}
