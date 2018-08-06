<?php

namespace bdk;

use bdk\Config;

/**
 * PDF
 */
class Pdf extends Config
{

	private $debug = null;
	protected static $cfgStatic = array(
		'license'		=> null,
		'licensefile'	=> null, // __FILE__.'/pdflib_license.txt',
	);
	private $cfg = array();
	private $embeddedFonts = array(
		'Courier',
		'Courier-Bold',
		'Courier-Oblique',
		'Courier-BoldOblique',
		'Helvetica',
		'Helvetica-Bold',
		'Helvetica-Oblique',
		'Helvetica-BoldOblique',
		'Times-Roman',
		'Times-Bold',
		'Times-Italic',
		'Times-BoldItalic',
		'Symbol',
		'ZapfDingbats',
	);
	private $p = null;	// pdflib instance
	private $pvf = '/pvf/pdf';

	/**
	 * Constructor
	 *
	 * @param array $cfg configuration
	 */
	public function __construct($cfg = array())
	{
		$this->debug = \bdk\Debug::getInstance();
		$this->cfg = array_merge(self::getCfg(), $cfg);
	}

	/**
	 * getCfg description
	 *
	 * @param mixed $path config path
	 *
	 * @return mixed
	 */
	public static function getCfg($path = null)
	{
		if (self::$cfgStatic['licensefile'] === null) {
			self::$cfgStatic['licensefile'] = __FILE__.'/pdflib_license.txt';
		}
		parent::getCfg($path);
	}

	/**
	 * attempt to transform the block name
	 *
	 * @param string $name  name
	 * @param array  $block block as returned by getBlocks() and getProperties()
	 * @param array  $opts  options
	 *
	 * @return string
	 */
	private function blockNameTrans($name, $block, &$opts)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$func = $opts['name_trans_func'][0];
		$params = array_slice($opts['name_trans_func'], 1);
		$strObj = new Str();
		if (!isset($opts['trans_func_params_sub'])) {
			$opts['trans_func_params_sub'] = array();
			foreach ($params as $k => $v) {
				$params[$k] = $strObj->quickTemp($v, $block);
				if ($params[$k] != $v) {
					$opts['trans_func_params_sub'][] = $k;
				}
			}
		} else {
			foreach ($opts['trans_func_params_sub'] as $k) {
				$params[$k] = $strObj->quickTemp($params[$k], $block);
			}
		}
		$name_new = call_user_func_array($func, $params);
		$this->debug->groupEnd();
		return $name_new;
	}

	/**
	 * createXFDF
	 *
	 * Takes values passed via associative array and generates XFDF file format
	 * with that data for the pdf address supplied.
	 *
	 * @param string $file The pdf file - url or file path accepted
	 * @param array  $vals data to use in key/value pairs no more than 2 dimensions
	 * @param string $enc  default UTF-8, match server output: default_charset in php.ini
	 *
	 * @return string The XFDF data for acrobat reader to use in the pdf form file
	 */
	public function createXFDF($file, $vals, $enc = 'UTF-8')
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$xml = '<?xml version="1.0" encoding="'.$enc.'"?>'."\n".
			'<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">'."\n".
			'<fields>'."\n";
		foreach ($vals as $field => $val) {
			$xml .= '<field name="'.$field.'">'."\n";
			if (is_array($val)) {
				foreach ($val as $opt) {
					$xml .= "\t".'<value>'.htmlentities($opt, ENT_COMPAT, $enc).'</value>'."\n";
				}
			} else {
				$xml .= "\t".'<value>'.htmlentities($val, ENT_COMPAT, $enc).'</value>'."\n";
			}
			$xml .= '</field>'."\n";
		}
		$xml .= '</fields>'."\n".
			'<ids original="'.md5($file).'" modified="'.time().'" />'."\n".
			'<f href="'.$file.'" />'."\n".
			'</xfdf>'."\n";
		$this->debug->groupEnd();
		return $xml;
	}

	/**
	 * will attempt to obtain license in order of preference
	 *	$GLOBALS['pdflib_cfg']['license']
	 *	$GLOBALS['pdflib_cfg']['licensefile']
	 *
	 * @param mixed $pdfTemplate may be a filepath, raw pdf data, or pdflib object
	 * @param array $values      key/value pairs
	 * @param array $opts        options
	 *
	 * @return mixed filepath to filled pdf, raw pdf data, or false on failure
	 */
	public function fill($pdfTemplate, $values, $opts = array())
	{
		$this->debug->group(__CLASS__.'->'.__FUNCTION__);
		$opts = $this->getFillOpts($opts);
		#$this->debug->log('temp_dir', $temp_dir);
		#$this->debug->log('pdf_template', $pdf_template);
		#$this->debug->log('pdflib_cfg', $GLOBALS['pdflib_cfg']);
		#$this->debug->log('opts', $opts);
		$return = false;
		$temp_dir = sys_get_temp_dir();
		try {
			$this->debug->log('output_file', $opts['output_file']);
			$p = $this->getPDFlib();
			$begin_doc_options = $this->stringifyOpts(array(
				'linearize'		=> true,	// !empty($opts['output_file'])	// On MVS systems this option cannot be combined with an empty filename. Default: false
				'tempdirname'	=> $temp_dir,
			));
			$this->debug->log('begin_doc_options', $begin_doc_options);
			if ($p->begin_document($opts['output_file'], $begin_doc_options)) {
				$this->debug->log('begining pdf');
				foreach ($opts['info'] as $k => $v) {
					$p->set_info($k, $v);
				}
				$pdiNo = $this->openPdf($pdfTemplate);
				$this->debug->log('pdiNo', $pdiNo);
				if ($pdiNo) {
					$page_count = $p->pcos_get_number($pdiNo, '/Root/Pages/Count');
					for ($pageNo = 0; $pageNo < $page_count; $pageNo++) {
						$this->fillPage($pdiNo, $pageNo, $values, $opts);
					}
					$p->close_pdi_document($pdiNo);
					if (empty($pdfTemplate)) {
						// can't delete until closed
						$ret = $p->delete_pvf($this->pvf);
					}
				}
				$p->end_document('');
				if (empty($opts['output_file'])) {
					$this->debug->info('returning raw pdf');
					$return = $p->get_buffer();
				} else {
					$this->debug->info('returning filepath');
					$return = $opts['output_file'];
				}
			} else {
				$this->debug->log('unable to begin pdf');
				$return = false;
			}
			$p->delete();
		} catch (PDFlibException $e) {
			trigger_error('PDFlib exception ['.$e->get_errnum().'] '.$e->get_apiname().' : '.$e->get_errmsg());
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * fill blocks on a page
	 *
	 * @param integer $pdiNo  int returned from open_pdi_document
	 * @param integer $pageNo page number
	 * @param array   $values key/values
	 * @param array   $opts   options
	 *
	 * @return void
	 */
	private function fillPage($pdiNo, $pageNo, $values, $opts)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $pageNo);
		$p = $this->p;
		$arrayUtil = new ArrayUtil();
		$page	= $p->open_pdi_page($pdiNo, ($pageNo+1), '');
		$width	= $p->pcos_get_number($pdiNo, 'pages['.$pageNo.']/width');
		$height	= $p->pcos_get_number($pdiNo, 'pages['.$pageNo.']/height');
		$p->begin_page_ext($width, $height, '');
		$p->fit_pdi_page($page, 0, 0, 'adjustpage');
		$blocks = $this->getBlocks($pdiNo, array(
			'page'	=> $pageNo,
			'props' => array('Name','Subtype','fontname','Custom'),
		));
		// now sort by the custom sort property
		//    some block may occupy the same space.. blocks will cover blocks written earlier
		foreach ($blocks as $k_block => $block) {
			$blocks[$k_block]['sort'] = isset($block['Custom']['sort'])
				? $block['Custom']['sort']
				: 0;
		}
		$blocks = $arrayUtil->fieldSort($blocks, array('sort','ID'));
		$this->debug->table('blocks', $blocks);
		foreach ($blocks as $k_block => $block) {
			$k_block_trans = !empty($opts['name_trans_func'])
				? $this->blockNameTrans($k_block, $block, $opts)
				: $k_block;
			#$this->debug->info('k_block', $k_block);
			#$this->debug->log('k_block_trans', $k_block_trans);
			#$this->debug->log('block', $block);
			$type	= $block['Subtype'];
			$ret	= true;
			$value	= null;
			#$this->debug->log('value', $value);
			if ($type == 'Text') {
				$name = isset($block['Custom']['PDFlib:field:name'])
					? $block['Custom']['PDFlib:field:name']
					: null;
				#$this->debug->log('name', $name);
				foreach (array($name,$k_block,$k_block_trans) as $k) {
					if (isset($values[$k])) {
						$value = $values[$k];
						break;
					}
				}
				$this->fillText($page, $block, $value, $opts);
			} elseif ($type == 'Image') {
				foreach (array($k_block,$k_block_trans) as $k) {
					if (isset($values[$k])) {
						$value = $values[$k];
						break;
					}
				}
				if (file_exists($value)) {
					$img = $p->load_image('auto', $value, '');
					$ret = $p->fill_imageblock($page, $block['Name'], $img, '');	// $this->stringifyOpts($opts_b)
					$p->close_image($img);
				}
			}
			if ($ret == 0) {
				$this->debug->log('error', $p->get_apiname().': '.$p->get_errmsg());
			}
		}
		$this->debug->log('closing page', $pageNo);
		$ret_a = $p->end_page_ext('');
		$ret_b = $p->close_pdi_page($page);
		$this->debug->log('ret_a', $ret_a);
		$this->debug->log('ret_b', $ret_b);
		$this->debug->groupEnd();
		return;
	}

	/**
	 * fill text block
	 *
	 * @param integer $page     page
	 * @param array   $block    block
	 * @param string  $value    value
	 * @param array   $fillOpts options
	 *
	 * @return void
	 */
	private function fillText($page, $block, $value, $fillOpts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $block['Name'], $value);
		$p = $this->p;
		$opts['fillcolor'] = $fillOpts['fillcolor'];
		if (!empty($block['Custom']['PDFlib:field:type'])
			&& in_array($block['Custom']['PDFlib:field:type'], array('checkbox','radiobutton'))
		) {
			$field_type		= $block['Custom']['PDFlib:field:type'];
			$value_block	= $block['Custom']['PDFlib:field:value'];
			#$this->debug->log('type', $field_type);
			#$this->debug->log('value_block', $value_block);
			#$this->debug->log('value', $value);
			if ($field_type == 'checkbox') {
				$opts = array_merge($opts, $fillOpts['checkbox_opts']);
				#$this->debug->log('checkbox_opts', $opts);
				if ($value == $value_block || is_array($value) && in_array($value_block, $value)) {
					$value = $fillOpts['checkbox_value'];
				}
			} else {
				$opts = array_merge($opts, $fillOpts['radio_opts']);
				#$this->debug->log('radio_opts', $opts);
				if ($value == $value_block) {
					$value = $fillOpts['radio_value'];
				}
			}
			if (!$value) {
				$opts['textformat'] = null;
				$value = ' ';	 // gives the border
			}
		} else {
			$opts = array_merge($opts, $fillOpts['text_opts']);
		}
		/*
			@todo - better determination of if font is embedded or avail -> better fallback choice
		*/
		/*
		$fontname = !empty($opts['fontname'])
			? $opts['fontname']
			: $block['fontname'];
		if ( !in_array($fontname, $this->embeddedFonts) ) {
			$this->debug->info(font not embedded', $fontname);
			$opts['fallbackfonts'] = '{{fontname=Courier encoding=unicode}}';
		}
		*/
		// $opts['fontname'] = 'Courier';
		$this->debug->log($block['Name'], $value, $this->stringifyOpts($opts));
		$ret = $p->fill_textblock($page, $block['Name'], $value, $this->stringifyOpts($opts));
		if ($ret == 0) {
			// try again with a different font
			$opts['fontname'] = 'Courier';
			$ret = $p->fill_textblock($page, $block['Name'], $value, $this->stringifyOpts($opts));
		}
		$this->debug->groupEnd();
	}

	/**
	 * Get PDF blocks
	 *
	 * @param integer $pdiNo document number
	 * @param array   $opts  options
	 *
	 * @return array
	 */
	public function getBlocks($pdiNo, $opts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$p = $this->p;
		$arrayUtil = new ArrayUtil();
		$opts = array_merge(array(
			'page'		=> null,
			'props'		=> array(),	// the properties to return (or all if empty)
			), $opts);
		#debug('opts', $opts);
		$blocks = array();
		try {
			if ($opts['page'] === null) {
				$this->debug->log('get blocks for all pages');
				$page_count = $p->pcos_get_number($pdiNo, '/Root/Pages/Count');
				$page_start = 0;
				$page_end	= $page_count - 1;
			} else {
				$page_start = $opts['page'];
				$page_end	= $opts['page'];
			}
			if (!empty($opts['props']) && !in_array('ID', $opts['props'])) {
				$opts['props'][] = 'ID';	// need ID for sorting
			}
			for ($pageNo = $page_start; $pageNo <= $page_end; $pageNo++) {
				$this->debug->log('pageNo', $pageNo);
				$block_count = $p->pcos_get_number($pdiNo, 'length:pages['.$pageNo.']/blocks');
				for ($i = 0; $i < $block_count; $i++) {
					$path = 'pages['.$pageNo.']/blocks['.$i.']';
					$block = $this->getProperties($pdiNo, $path, $opts);
					$block['page'] = $pageNo;
					#$this->debug->log('block',$block);
					$blocks[ $block['Name'] ] = $block;
				}
			}
		} catch ( PDFlibException $e ) {
			$this->debug->warn('exception: '.$e->faultcode.', faultstring: '.$e->faultstring);
		}
		$blocks = $arrayUtil->fieldSort($blocks, array('page','ID'));
		$this->debug->log('blocks', $blocks);
		$this->debug->groupEnd();
		return $blocks;
	}

	/**
	 * Get options used by fill()
	 *
	 * @param @array $opts options
	 *
	 * @return array
	 */
	private function getFillOpts($opts)
	{
		$this->debug->group(__CLASS__.'->'.__FUNCTION__);
		$arrayUtil = new ArrayUtil();
		$opts = $arrayUtil->mergeDeep(array(
			'output_file'	=> 'temporary',	// if null is passed, raw pdf will be returned
			'fillcolor'		=> null,		// font-color
			'checkbox_opts'	=> array(
				'fontname'		=> 'ZapfDingbats',
				'textformat'	=> 'utf16be',
				'encoding'		=> 'unicode',
				'position'		=> 'center',
				'bordercolor'	=> '#C0C0C0',	// or null
				// 'fontsize'	=> 12,
			),
			'checkbox_value'	=> "\x27\x18",
			'radio_opts'	=> array(
				'fontname'		=> 'ZapfDingbats',
				'textformat'	=> 'utf16be',
				'encoding'		=> 'unicode',
				'position'		=> 'center',
				'bordercolor'	=> '#C0C0C0',	// or null
				// 'fontsize'	=> 12,
			),
			'radio_value'	=> "\x25\xCF",
			'text_opts'		=> array(
				'bordercolor'	=> 'none',
			),
			'name_trans_func'	=> array(),			// function and parameters to transform block name
			'info' => array(),
		), $opts);
		if ($opts['output_file'] == 'temporary') {
			$temp_dir = sys_get_temp_dir();
			$opts['output_file'] = tempnam($temp_dir, 'pdf');
		}
		$this->debug->groupEnd();
		return $opts;
	}

	/**
	 * Create & return PDFlib instance
	 *
	 * @param array $opts options
	 *
	 * @return object PDFlib
	 */
	public function getPDFlib($opts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		debug('opts', $opts);
		$optsDefault = array(
			'errorpolicy'	=> 'return',		// This means we must check return values of load_font() etc.
			'license'		=> null,
			'textformat'	=> 'utf8',
			// 'avoiddemostamp'=> true,			// throws exception when true && invalid license
			'SearchPath'	=> $_SERVER['DOCUMENT_ROOT'],
			// 'escapesequence'	=> 'true',
		);
		$opts = array_merge($optsDefault, $opts);
		if (empty($opts['license'])) {
			if (isset($this->cfg['license'])) {
				$opts['license'] = $this->cfg['license'];
			} elseif (file_exists($this->cfg['licensefile'])) {
				$this->debug->info('licensefile found', $this->cfg['licensefile']);
				$opts['license'] = trim(file_get_contents($this->cfg['licensefile']));
			}
		}
		$p = new \PDFlib();
		$this->p = $p;
		$pdflibVer = $p->get_parameter('version', 0);
		$this->debug->info('pdflib version', $pdflibVer);
		$this->debug->log('opts', $opts);
		try {
			if (version_compare($pdflibVer, '9.0.0', '>=')) {
				$opt_string = $this->stringifyOpts($opts);
				$response = $p->set_option($opt_string);
			} else {
				foreach ($opts as $k => $v) {
					$response = $p->set_parameter($k, $v);
				}
			}
		} catch ( PDFlibException $e ) {
			trigger_error('PDFlib exception ['.$e->get_errnum().'] '.$e->get_apiname().' : '.$e->get_errmsg());
		}
		$this->debug->groupEnd();
		return $p;
	}

	/**
	 * pdflib_get_properties
	 *    returns something that may look like
	 *			array(
	 *				[Custom] => Array(
	 *					[PDFlib:field:name] => OAOIntAR[1].AcctLimit10[1]
	 *					[PDFlib:field:pagenumber] => 1
	 *					[PDFlib:field:type] => textfield
	 *				)
	 *				[ID] => 31
	 *				[Name] => OAOIntAR_1_.AcctLimit10_1_
	 *				[Subtype] => Text
	 *				[fontname] => Courier New Bold
	 *				[path] => pages[0]/blocks[0]
	 *			)
	 *
	 * @param integer $pdiNo document num
	 * @param string  $path  path
	 * @param array   $opts  optional
	 *
	 * @return array
	 */
	public function getProperties($pdiNo, $path, $opts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__, $path);
		$p = $this->p;
		$opts = array_merge(array(
			'props'			=> array(),	// the properties to return (or all if empty)
			'parentType'	=> null,
			), $opts);
		#debug('opts', $opts);
		$props = array();
		if (empty($opts['parentType'])) {
			$props['path'] = $path;
		}
		try {
			$propcount = $p->pcos_get_number($pdiNo, 'length:'.$path);
			#$this->debug->log('path', $path);
			#$this->debug->log('propcount', $propcount);
			for ($i=0; $i<$propcount; $i++) {
				$path_prop = $path.( preg_match('#[\w+][\d+]$#', $path)
					? '/['.$i.']'
					: '['.$i.']'
				);
				$type = $p->pcos_get_string($pdiNo, 'type:'.$path_prop);
				#$this->debug->log($path_prop, $opts['parentType'], $type);
				$key = !empty($opts['parentType']) && $opts['parentType'] == 'array' || empty($opts['parentType']) && preg_match('#^\w+$#', $path)
					? $i
					: $p->pcos_get_string($pdiNo, $path_prop.'.key');
				#$this->debug->log('key', $key);
				if (empty($opts['parentType'])) {
					if (!empty($opts['props']) && !in_array($key, $opts['props'])) {
						continue;
					} elseif ($key === 'AcroForm') {
						continue;
					}
				}
				if (in_array($type, array('name','string'))) {
					$val = $p->pcos_get_string($pdiNo, $path_prop);	// .'.val'
				} elseif (in_array($type, array('number','boolean'))) {
					$val = $p->pcos_get_number($pdiNo, $path_prop);	// .'.val'
					if ($type == 'boolean') {
						$val = !empty($val);
					}
				} elseif (in_array($type, array('dict','array'))) {
					$val = call_user_func(array($this,__FUNCTION__), $pdiNo, $path_prop, array(
						'parentType'=>$type,
					));
				} else {
					$val = null;
					$this->debug->info($key, $type);
				}
				$props[$key] = $val;
			}
		} catch (PDFlibException $e) {
			trigger_error('PDFlib exception ['.$e->get_errnum().'] '.$e->get_apiname().' : '.$e->get_errmsg());
		}
		$this->debug->log('props', $props);
		$this->debug->groupEnd();
		return $props;
	}

	/**
	 * Merge 2 or more pdfs
	 *
	 * @param array $pdfs an array of pdfs to merge together.  may be a mixture of filepaths, raw pdf data, and pdflib objects
	 * @param array $opts options
	 *
	 * @return mixed
	 */
	public function mergePdfs($pdfs, $opts = array())
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		#$this->debug->log('pdfs',$pdfs);
		$return = false;
		$temp_dir = sys_get_temp_dir();
		$opts = array_merge_replace(array(
			'output_file' => 'temporary',	// if null is passed, pdf object will be returned
			/*
			'parameters' => array(
				'license'		=> null,
				'errorpolicy'	=> 'return',
				'SearchPath'	=> $_SERVER['DOCUMENT_ROOT'],
				// 'escapesequence'	=> 'true',
			),
			*/
			'info' => array(),
		), $opts);
		if ($opts['output_file'] == 'temporary') {
			$opts['output_file'] = tempnam($temp_dir, 'pdf');
		}
		$this->debug->log('output_file', $opts['output_file']);
		try {
			$p = getPDFlib();
			if ($p->begin_document($opts['output_file'], 'tempdirname='.$temp_dir)) {
				foreach ($opts['info'] as $k => $v) {
					$p->set_info($k, $v);
				}
				foreach ($pdfs as $pdf) {
					$pdfNo = $this->openPdf($pdf);
					if (!$pdfNo) {
						$this->debug->log('error', 'error opening');
						continue;
					}
					$endpage = $p->pcos_get_number($pdfNo, 'length:pages');
					/*
						Loop over all pages of the input document
					*/
					for ($pageNo = 1; $pageNo <= $endpage; $pageNo++) {
						$page = $p->open_pdi_page($pdfNo, $pageNo, '');
						if (!$page) {
							$this->debug->log('error', 'error opening pageno '.$pageno);
							continue;
						}
						// Dummy $page size; will be adjusted later
						$p->begin_page_ext(10, 10, '');
						/*
						// Create a bookmark with the file name
						if ($pageno == 1) {
							$p->create_bookmark($pdffile, '');
						}
						*/
						/*
							Place the imported $page on the output $page, and
							adjust the $page size
						*/
						$p->fit_pdi_page($page, 0, 0, 'adjustpage');
						$p->close_pdi_page($page);
						$p->end_page_ext('');
					}
					$p->close_pdi_document($pdf_in);
					if (empty($pdf)) {
						// can't delete until closed
						$ret = $p->delete_pvf($this->pvf);
					}
				}
				$p->end_document('');
				if (empty($opts['output_file'])) {
					$this->debug->info('returning raw pdf');
					$return = $p->get_buffer();
				} else {
					$this->debug->info('returning filepath');
					$return = $opts['output_file'];
				}
			} else {
				$this->debug->log('unable to begin pdf');
				$return = false;
			}
			$p->delete();
		} catch (PDFlibException $e) {
			trigger_error('PDFlib exception ['.$e->get_errnum().'] '.$e->get_apiname().' : '.$e->get_errmsg());
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Get PDI document
	 *
	 * @param mixed $pdfTemplate input pdf filepath, raw pdf, or object
	 *
	 * @return integer
	 */
	public function openPdf($pdfTemplate)
	{
		$this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
		$p = $this->p;
		$pdiOpts = 'errorpolicy=return';
		if (is_object($pdfTemplate) || preg_match('/[\\x00-\\x1f]/', $pdfTemplate)) {
			if (is_object($pdfTemplate)) {
				$this->debug->log('pdfTemplate is object');
				$p->create_pvf($this->pvf, $pdfTemplate->get_buffer(), '');
				$pdfTemplate->delete();
			} else {
				$this->debug->log('pdfTemplate is raw pdf data');
				$p->create_pvf($this->pvf, $pdfTemplate, '');
			}
			$pdfTemplate = null;
			$pdiNo = $p->open_pdi_document($this->pvf, $pdiOpts);
		} else {
			$this->debug->log('pdfTemplate', $pdfTemplate);
			$this->debug->log('file_exists', file_exists($pdfTemplate));
			try {
				$pdiNo = $p->open_pdi_document($pdfTemplate, $pdiOpts);
			} catch ( PDFlibException $e ) {
				trigger_error('PDFlib exception ['.$e->get_errnum().'] '.$e->get_apiname().' : '.$e->get_errmsg());
			}
			$this->debug->info('get_errmsg', $p->get_errmsg());
		}
		$this->debug->groupEnd();
		return $pdiNo;
	}

	/**
	 * Create option string as used by pdflib
	 *
	 * @param array $opts key/values to stringify
	 *
	 * @return string
	 */
	public function stringifyOpts($opts)
	{
		$pairs = array();
		foreach ($opts as $k => $v) {
			if (is_null($v)) {
				continue;
			}
			if ($v === true) {
				$pairs[] = $k;
			} elseif ($v === false) {
				$pairs[] = $k.'=false';
			} else {
				$pairs[] = $k.'='.$v;
			}
		}
		return implode(' ', $pairs);
	}
}
