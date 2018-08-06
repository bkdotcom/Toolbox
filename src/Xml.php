<?php

namespace bdk;

use bdk\Str;

/**
 * XML
 */
class Xml
{

	private $debug = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->debug = \bdk\Debug::getInstance();
	}

	/**
	 * xml parsing tutorial: http://www.ibm.com/developerworks/xml/library/x-xmlphp2.html
	 *
	 * @param string $xml  XML
	 * @param array  $opts options
	 * @param array  $path @internal
	 *
	 * @return array
	 */
	public static function toArray($xml, $opts = array(), $path = array())
	{
		$debug = \bdk\Debug::getInstance();
		$debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, implode('/', $path));
		if (empty($opts['prepped'])) {
			$optsDefault = array(
				'attribs'		=> true,	// collect attributes?
				'attribs_array'	=> true,	// if false attribs are given "_" prefix
				'collapse'		=> false,
				'collect'		=> false,	// whether to "collect"/bundle tags together and key arrays with tag-name
				'empty_props'	=> true,	// whether empty values/structures will be returned (true) or absent (false)
				'lower_keys'	=> false,	// convert tags to lower case
			);
			if (!empty($opts['collapse'])) {
				$opts['collect'] = true;
				$optsDefault['empty_props'] = false;
				$optsDefault['lower_keys'] = true;
			}
			$opts = array_merge($optsDefault, $opts);
			$opts['prepped'] = true;
		}
		if (is_string($xml)) {
			$xmlReader = new \XMLReader();
			if (!strpos($xml, "\n") && strpos($xml, '<') === false) {
				$debug->log('xml is a file or url');
				$xmlReader->open($xml);
			} else {
				$xmlReader->xml($xml);
			}
			$xml = $xmlReader;
		}
		$tree = array();
		if (!is_object($xml)) {
			trigger_error('xml is a non-object');
			$debug->log('xml', $xml);
			$tree = false;
		} else {
			while ($xml->read()) {
				if ($xml->nodeType == \XMLReader::END_ELEMENT) {
					break;
				} elseif ($xml->nodeType == \XMLReader::ELEMENT) {
					// $debug->info('opening', $xml->name);
					$pathCurrent = $path;
					$pathCurrent[] = $xml->name;
					$node = array(
						'tag'		=> $xml->localName,
						'prefix'	=> $xml->prefix,
						'attribs'	=> array(),
						'text'		=> '',
						'nodes'		=> array(),
					);
					if ($opts['collapse'] && $node['tag'] == 'text') {
						$node['tag'] = '_'.$node['tag'];
					}
					if ($opts['lower_keys']) {
						$node['tag']	= strtolower($node['tag']);
						$node['prefix'] = strtolower($node['prefix']);
					}
					$is_empty = $xml->isEmptyElement;
					if ($opts['attribs'] && $xml->hasAttributes) {
						while ($xml->moveToNextAttribute()) {
							if ($opts['attribs_array']) {
								$node['attribs'][$xml->name] = $xml->value;
							} else {
								$node['_'.$xml->name] = $xml->value;
							}
						}
					}
					$node['nodes'] = !$is_empty
						? self::toArray($xml, $opts, $pathCurrent)
						: array();
					if (count($node['nodes']) == 1 && isset($node['nodes'][0]) && count($node['nodes'][0]) == 1) {
						$node['text'] = $node['nodes'][0]['text'];
						$node['nodes'] = array();
					}
					if (!$opts['empty_props']) {
						if (!empty($node['nodes']) && empty($node['text'])) {
							unset($node['text']);
						}
						foreach ($node as $k => $v) {
							if ($k != 'text' && ( $v === '' || $v === array() )) {
								unset($node[$k]);
							}
						}
						if (array_keys($node) == array('tag')) {
							$node = array();
						}
					} elseif (empty($node['prefix'])) {
						unset($node['prefix']);
					}
					if (!empty($node) && $opts['collect']) {
						$name = $node['tag'];
						if ($opts['collapse'] && !empty($node['prefix'])) {
							// $debug->warn('prefix + collapse');
							$name = $node['prefix'].':'.$name;
						}
						if (!isset($tree[$name])) {
							$tree[$name] = array();
						}
						unset($node['tag']);
						if (!is_array($tree[$name])) {
							$tree[$name] = array ( $tree[$name] );
						}
						$tree[$name][] = $node;
						if ($opts['collapse']) {
							array_pop($tree[$name]);
							if (empty($tree[$name])) {
								unset($tree[$name]);
							}
							if (!empty($node['prefix'])) {
								// $name = $node['prefix'].':'.$name;	// moved above
								unset($node['prefix']);
							}
							if (!empty($node['nodes'])) {
								foreach ($node['nodes'] as $k => $v) {
									$node[$k] = $v;
								}
								unset($node['nodes']);
							} elseif (isset($node['text'])) {
								if (count($node) == 1) {
									$node = $node['text'];
								}
							} elseif (empty($node)) {
								$node = '';
							}
							if (isset($tree[$name])) {
								if (!is_array($tree[$name]) || ArrayUtil::isHash($tree[$name])) {
									$tree[$name] = array( $tree[$name] );
								}
								$tree[$name][] = $node;
							} else {
								$tree[$name] = $node;
							}
						}
					} elseif (!empty($node)) {
						$tree[] = $node;
					}
				} elseif ($xml->hasValue && in_array($xml->nodeType, array(\XMLReader::TEXT, \XMLReader::CDATA))) {
					$tree[] = array(
						'text' => trim($xml->value),
					);
				}
			}
		}
		$debug->groupEnd();
		return $tree;
	}

	/**
	 * build xml from array
	 *
	 * @param array $array to convert to xml
	 * @param array $opts  options
	 *
	 * @return string
	 */
	public function build($array, $opts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$opts = array_merge(array(
			'xml_tag'		=> false,
			'xml_version'	=> '1.0',
			'xml_encoding'	=> 'utf-8',
			'empty_tags'	=> false,
			'empty_attribs' => false,
			'indent'		=> "\t",
			'wrap_tag'		=> null,
			'wrap_attribs'	=> array(),
			'debug_group'	=> false,
		), $opts);
		$w = new \XMLWriter();
		$w->openMemory();
		if ($opts['indent']) {
			$w->setIndent(true);
			$w->setIndentString("\t");
		}
		if ($opts['xml_tag']) {
			$w->startDocument($opts['xml_version'], $opts['xml_encoding']);
		}
		if ($opts['wrap_tag']) {
			$w->startElement($opts['wrap_tag']);
			foreach ($opts['wrap_attribs'] as $k => $v) {
				$w->writeAttribute($k, $v);
			}
		}
		$path = array();
		$levels = array(
			array(
				'startElement'		=> false,
				'keys'				=> array_keys($array),
				'pointer_source'	=> &$array,
			),
		);
		while (!empty($levels)) {
			$level =& $levels[count($levels)-1];
			if (empty($level['prepped'])) {
				#$this->debug->log('prepping');
				$level['prepped'] = true;
				#$this->debug->log('path', implode('/', $path));
				// make sure attribs is first in keys
				$key = array_search('attribs', $level['keys']);
				if ($key > 0) {
					unset($level['keys'][$key]);
					array_unshift($level['keys'], 'attribs');
				}
			}
			if (!empty($level['keys'])) {
				$key = array_shift($level['keys']);
				$val = &$level['pointer_source'][$key];
				$name = $key;
				if (is_int($key)) {
					$name = is_array($val) && isset($val['tag'])
						? $val['tag']
						: $path[count($path)-1];
				}
				#$this->debug->log('name a',$name);
				$collapsed = true;
				if (is_array($val)) {
					foreach ($val as $k => $v) {
						if (substr($k, 0, 1) == '_' && $k != '_text') {
							$k2 = substr($k, 1);
							$level['pointer_source'][$key]['attribs'][$k2] = $v;
							unset($level['pointer_source'][$key][$k]);
						}
					}
					if (isset($val['tag']) || isset($val['nodes']) || isset($val['text'])) {
						$collapsed = false;
						if (isset($val['tag'])) {
							$name = $val['tag'];
						}
						if (isset($val['prefix'])) {
							$name = $val['prefix'].':'.$name;
						}
					}
				}
				#$this->debug->log('name b',$name);
				if ($val === null || $val === array() || $val === '') {
					if ($opts['empty_tags']) {
						$w->writeElement($name, $val);
					}
					// else
					//	$this->debug->log('skip '.$key);
				} elseif (is_string($val) && $key === 'text') {
					$val = Str::toUtf8($val);
					$w->text($val);
				} elseif (is_array($val) && $key === 'attribs') {
					foreach ($val as $k => $v) {
						if (is_null($v)) {
							continue;
						}
						$w->writeAttribute($k, $v);
					}
				} elseif (is_array($val)) {
					if ($opts['debug_group']) {
						$this->debug->groupCollapsed($key);
					}
					#$this->debug->log('collapsed', $collapsed);
					if ($collapsed) {
						$level_new = array(
							'startElement'		=> ArrayUtil::isHash($val),
							'keys'				=> array_keys($val),
							'pointer_source'	=> &$level['pointer_source'][$key],
						);
					} else {
						$level_new = array(
							'startElement'		=> true,
							'keys'				=> !empty($val['nodes'])
														? array_keys($val['nodes'])
														: array(),
							'pointer_source'	=> isset($val['text'])
														? $val['text']
														: null,
						);
						if (!empty($val['nodes'])) {
							$level_new['pointer_source'] =& $level['pointer_source'][$key]['nodes'];
						}
					}
					#$this->debug->log('level_new', $level_new);
					$levels[] = $level_new;
					$path[] = $key;
					if ($level_new['startElement']) {
						$w->startElement($name);
						if (!$collapsed && !empty($val['attribs'])) {
							foreach ($val['attribs'] as $k => $v) {
								$w->writeAttribute($k, $v);
							}
						}
						if (empty($level_new['keys']) && isset($level_new['pointer_source']) && $level_new['pointer_source'] !== '') {
							$w->text($level_new['pointer_source']);
						}
					}
				} else {
					if ($name == '_text') {
						$name = 'text';
					}
					$val = Str::toUtf8($val);
					$w->writeElement($name, $val);
				}
			} else {
				array_pop($levels);
				$p = array_pop($path);
				if ($level['startElement']) {
					$w->endElement();
				}
				if ($opts['debug_group'] && $p !== null) {
					$this->debug->groupEnd();
				}
			}
		}
		if ($opts['wrap_tag']) {
			$w->endElement();
		}
		if ($opts['xml_tag']) {
			$w->endDocument();
		}
		$xml = $w->flush();
		if (!$opts['empty_tags']) {
			$xml = preg_replace('#'.$opts['indent'].'*<[\w:]+/>\n#', '', $xml);
		}
		$this->debug->groupEnd();
		return $xml;
	}
}
