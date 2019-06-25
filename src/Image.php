<?php

namespace bdk;

/**
 * Image manipulation and related funcs
 */
class Image
{

	protected $debug = null;
	protected $imageTypes = array(
		'image/gif'		=> IMAGETYPE_GIF,		// 1
		'image/jpeg'	=> IMAGETYPE_JPEG,		// 2
		'image/png'		=> IMAGETYPE_PNG,		// 3
		'image/bmp'		=> IMAGETYPE_BMP,		// 6
		'image/tiff'	=> IMAGETYPE_TIFF_II,	// 7
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->debug = \bdk\Debug::getInstance();
	}

	/**
	 * fpSrc values:
	 *	filepath
	 *	'data'	: pass image-data via $vals['src_data']
	 * fpDst values:
	 * 	filepath
	 *	null	: image will be output / headers will be output if output_headers is true (default)
	 *	'data'	: image data will returned
	 * vals: dst_x,dst_y,src_x,src_y,dst_w,dst_h,src_w,src_h are all required
	 *	jpeg	- http://www.php.net/manual/en/function.imagejpeg.php
	 *	png		- http://www.php.net/manual/en/function.imagepng.php
	 *	gif		- http://www.php.net/manual/en/function.imagegif.php
	 *
	 * @param string $fpSrc file path or 'data'
	 * @param string $fpDst file path, null, or 'data'
	 * @param array  $vals  paramaters
	 *
	 * @return mixed
	 */
	public function crop($fpSrc, $fpDst = null, $vals = array())
	{
		$this->debug->group(__METHOD__);
		$return = false;
		$vals = array_merge(array(
			'src_type'	=> null,		// optional.. if not provided, will be determined
			'src_data'	=> null,		// used when fpSrc = 'data'
			'dst_type'			=> 'jpeg',	// extension or IMAGETYPE_XXX constant
			'output_headers'	=> true,	// only applies when fpDst is null
			'filename'			=> null,	// will be used to set filename header if being output
			'color_bg'			=> array(255,255,255),	// white... may also specify in hex: FFFFFF
			'color_bg_alpha'	=> 0,					// 0 = opaque, 127 = transparent
			'imagesavealpha'	=> false,		// sets the flag to attempt to save full alpha channel information (as opposed to single-color transparency) when saving PNG images.
			'quality'			=> 50,			// 0 - 100
			'opts'				=> array(),		// specify 3rd parameter on for imagexxx (save/output function)
			'interlace'			=> true,
			'src_w'	=> 0,		// defaults to src's full width
			'src_h'	=> 0,		// defaults to src's full height
			'src_x'	=> 0,
			'src_y'	=> 0,
			'dst_w' => null,	// defaults to src_w
			'dst_h' => null,	// defaults to src_h
			'dst_x'	=> 0,
			'dst_y'	=> 0,
		), $vals);
		$this->debug->log('fpSrc', $fpSrc);
		$this->debug->log('fpDst', $fpDst);
		ini_set('memory_limit', '80M');
		$vals = $this->cropDefaults($fpSrc, $vals);
		/*
		$debugVal = !empty($vals['src_data'])
			? array_merge($vals, array('src_data'=>'removed for debug'))
			: $vals;
		$this->debug->log('vals', $debugVal);
		*/
		// create src & dst images
		if ($fpSrc == 'data') {
			$imSrc = imagecreatefromstring($vals['src_data']);
		} elseif ($vals['src_type'] == 'bmp') {
			$imSrc = $this->imagecreatefrombmp($fpSrc);
		} else {
			$imSrc = call_user_func('imagecreatefrom'.$vals['src_type'], $fpSrc);
		}
		$imDst = imagecreatetruecolor($vals['dst_w'], $vals['dst_h']);
		if ($vals['interlace']) {
			imageinterlace($imDst, true);
		}
		// fill in bg color & set alpha info
		if (!$imSrc) {
			$this->debug->warn('invalid source');
		} elseif ($vals['imagesavealpha']) {
			imagealphablending($imDst, false);
			imagesavealpha($imDst, true);
		} else {
			$this->debug->log('not saving alpha');
			imagealphablending($imDst, true);
			// determine if image has a transparent color
			$trans_i_src = imagecolortransparent($imSrc);
			if ($trans_i_src != -1) {
				$this->debug->log('src has transparent color index', $trans_i_src);
				$trans_c_src = imagecolorsforindex($imSrc, $trans_i_src);
				$this->debug->log('trans_color_src', $trans_c_src);
				$trans_c_new = imagecolorallocate($imDst, $trans_c_src['red'], $trans_c_src['green'], $trans_c_src['blue']);
				imagecolortransparent($imDst, $trans_c_new);
				imagefill($imDst, 0, 0, $trans_c_new);
			}
		}
		$color_bg = $vals['color_bg_alpha']
			? imagecolorallocatealpha($imDst, $vals['color_bg'][0], $vals['color_bg'][1], $vals['color_bg'][2], $vals['color_bg_alpha'])
			: imagecolorallocate($imDst, $vals['color_bg'][0], $vals['color_bg'][1], $vals['color_bg'][2]);
		imagefilledrectangle($imDst, 0, 0, $vals['dst_w']-1, $vals['dst_h']-1, $color_bg);
		if (!$imSrc) {
			$this->debug->warn('invalid source');
		} elseif ($vals['dst_w'] == $vals['src_w'] && $vals['dst_h'] == $vals['src_h']) {
			$this->debug->log('imagecopy');
			$return = imagecopy(
				$imDst,
				$imSrc,
				$vals['dst_x'],
				$vals['dst_y'],
				$vals['src_x'],
				$vals['src_y'],
				$vals['src_w'],
				$vals['src_h']
			);
		} else {
			$this->debug->log('imagecopyresampled');
			$return = imagecopyresampled(
				$imDst,
				$imSrc,
				$vals['dst_x'],
				$vals['dst_y'],
				$vals['src_x'],
				$vals['src_y'],
				$vals['dst_w'],
				$vals['dst_h'],
				$vals['src_w'],
				$vals['src_h']
			);
		}
		if (!$vals['imagesavealpha'] && in_array($vals['dst_type'], array('png'))) {
			$this->debug->log('converting to 256 color palette');
			imagetruecolortopalette($imDst, false, 255);
		}
		if ($return) {
			$return = $this->cropOutput($imDst, $fpDst, $vals);
		}
		if ($imSrc) {
			imagedestroy($imSrc);
		}
		if ($imDst) {
			imagedestroy($imDst);
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * get default & normalize crop parameters
	 *
	 * @param string $fpSrc file path or 'data'
	 * @param array  $vals  paramaters
	 *
	 * @return mixed
	 */
	protected function cropDefaults($fpSrc, $vals)
	{
		$this->debug->groupcollapsed(__METHOD__);
		// src Defaults
		if (empty($vals['src_type']) || empty($vals['src_w']) || empty($vals['src_h'])) {
			// $this->debug->log('determine src info');
			$imageInfo = $fpSrc == 'data'
				? getimagesizefromstring($vals['src_data'])
				: getimagesize($fpSrc);
			// $this->debug->log('imageInfo', $imageInfo);
			if ($imageInfo) {
				$imageInfoVals = array(
					'src_type'	=> $imageInfo[2],
					'src_w'		=> $imageInfo[0],
					'src_h'		=> $imageInfo[1],
				);
				foreach (array('src_type','src_w','src_h') as $k) {
					if (empty($vals[$k])) {
						$vals[$k] = $imageInfoVals[$k];
					}
				}
			}
		}
		// $this->debug->log('vals', $vals);
		foreach (array('src_type','dst_type') as $type) {
			if (is_int($vals[$type])) {
				$vals[$type] = image_type_to_extension($vals[$type], false);	// false = no dot
			} elseif ($vals[$type] == 'jpg') {
				$vals[$type] = 'jpeg';
			}
		}
		if (empty($vals['dst_w'])) {
			$vals['dst_w'] = $vals['src_w'];
		}
		if (empty($vals['dst_h'])) {
			$vals['dst_h'] = $vals['src_h'];
		}
		if (is_string($vals['color_bg'])) {
			$vals['color_bg'] = array_values($this->hex2rgb($vals['color_bg']));
		}
		if (empty($vals['opts'])) {
			$quality = $vals['quality'];
			if ($vals['dst_type'] == 'png') {
				$vals['quality'] = round(abs(($quality - 100) / 11.111111));	// convert 0-100 "quality" to 0-9 "compression"
			}
			$vals['opts'] = array( $vals['quality'] );
		}
		// $this->debug->log('vals', $vals);
		$this->debug->groupEnd();
		return $vals;
	}

	/**
	 * [cropOutput description]
	 *
	 * @param resource $imDst image resource
	 * @param string   $fpDst filepath
	 * @param array    $vals  parameters
	 *
	 * @return mixed
	 */
	protected function cropOutput($imDst, $fpDst, $vals)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = true;
		// $this->debug->log('fpDst', $fpDst);
		$fpWas = $fpDst;
		if ($fpDst === 'data') {
			$fpDst = null;
		}
		$params = array_merge(array($imDst, $fpDst), $vals['opts']);
		// $this->debug->log('is_resource(imDst)', is_resource($imDst));
		// $this->debug->log('image'.$vals['dst_type'].'() params', $params);
		if (is_null($fpDst)) {
			// buffer when fpDst == null so that can determine resulting content-length
			$this->debug->log('buffer / capture output from image'.$vals['dst_type']);
			ob_start();
			if ($vals['src_type'] == 'bmp') {
				$return = $this->createbmp($imDst);
			} else {
				$return = call_user_func_array('image'.$vals['dst_type'], $params);
			}
			$this->debug->log('success', $return);
			$return = ob_get_contents();
			ob_end_clean();
			if ($fpWas == 'data') {
				$this->debug->log('returning buffered output / data');
			} else {
				$this->debug->log('echoing buffered output / data');
				$GLOBALS['page']['template'] = null;
				if ($vals['output_headers']) {
					$this->debug->log('outputing headers');
					header('Last Modified: '.gmdate('D, d M Y H:i:s \G\M\T'));
					header('Content-Type: image/'.$vals['dst_type']);
					if (isset($vals['filename'])) {
						header('Content-Disposition: inline; filename="'.$vals['filename'].'"');
					}
					header('Content-Length: '.strlen($return));
				}
				echo $return;
				$return = true;
			}
		} else {
			$this->debug->log('saving to file');
			$dirname = dirname($fpDst);
			if (!file_exists($dirname)) {
				$this->debug->log('creating directory');
				mkdir($dirname, 0777, true);
			}
			if ($vals['src_type'] == 'bmp') {
				$return = $this->createbmp($imDst, $fpDst);
			} else {
				$return = call_user_func_array('image'.$vals['dst_type'], $params);
			}
			$this->debug->log('success', $return);
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Get the dimensions to fit an image to maxw and maxH
	 *
	 * @param integer $curW current width
	 * @param integer $curH current height
	 * @param integer $maxW maximum width
	 * @param integer $maxH maximum height
	 *
	 * @return array
	 */
	public function getConstrainedDimensions($curW, $curH, $maxW, $maxH)
	{
	    $this->debug->groupCollapsed(__METHOD__);
	    $ratio = $curW / $curH;   // > 1 = wider than tall (landscape) < 1 = portrait
	    $return = array('w' => $curW, 'h' => $curH);
	    if ($return['w'] > $maxW) {
	        $this->debug->log('fitting to maxW');
	        $return = array(
	            'w' => $maxW,
	            'h' => round($maxW / $ratio)
	        );
	    }
	    if ($return['h'] > $maxH) {
	        $this->debug->log('fitting to maxH');
	        $return = array(
	            'w' => round($maxH * $ratio),
	            'h' => $maxH
	        );
	    }
	    // $this->debug->log('return', $return);
	    $this->debug->groupEnd();
	    return $return;
	}

	/**
	 * Convert a hexadecimal color code to its RGB equivalent
	 *
	 * @param string  $strHex         hexadecimal color value
	 * @param boolean $returnAsString if set true, returns the value separated by the separator character. Otherwise returns associative array
	 * @param string  $seperator      to separate RGB values. Applicable only if second parameter is true.
	 *
	 * @return array or string (depending on second parameter. Returns false if invalid hex color value)
	 */
	public function hex2rgb($strHex, $returnAsString = false, $seperator = ',')
	{
		$strHex = preg_replace("/[^0-9A-Fa-f]/", '', $strHex); // Gets a proper hex string
		$rgbArray = array();
		$return = false;
		if (strlen($strHex) == 6) {
			// If a proper hex code, convert using bitwise operation. No overhead... faster
			$colorVal = hexdec($strHex);
			$rgbArray = array(
				'red'	=> 0xFF & ($colorVal >> 0x10),
				'green'	=> 0xFF & ($colorVal >> 0x8),
				'blue'	=> 0xFF & $colorVal,
			);
		} elseif (strlen($strHex) == 3) {
			// if shorthand notation, need some string manipulations
			$rgbArray = array(
				'red'	=> hexdec(str_repeat(substr($strHex, 0, 1), 2)),
				'green'	=> hexdec(str_repeat(substr($strHex, 1, 1), 2)),
				'blue'	=> hexdec(str_repeat(substr($strHex, 2, 1), 2)),
			);
		}
		if (!empty($rgbArray)) {
			$return = $returnAsString
						? implode($seperator, $rgbArray)
						: $rgbArray; // returns the rgb string or the associative array
		}
		return $return;
	}

	/**
	 * Save 24bit BMP file
	 *
	 * @param resource $img      image resource
	 * @param boolean  $filename (optional) filepath to save to.. otherwise image is echoed
	 *
	 * @return boolean
	 */
	public function imagebmp(&$img, $filename = false)
	{
		$wid = imagesx($img);
		$hei = imagesy($img);
		$wid_pad = str_pad('', $wid % 4, "\0");
		$size = 54 + ($wid + $wid_pad) * $hei;
		// prepare & save header
		$header = array(
			'identifier'		=> 'BM',
			'file_size'			=> $this->dword($size),
			'reserved'			=> $this->dword(0),
			'bitmap_data'		=> $this->dword(54),
			'header_size'		=> $this->dword(40),
			'width'				=> $this->dword($wid),
			'height'			=> $this->dword($hei),
			'planes'			=> $this->word(1),
			'bits_per_pixel'	=> $this->word(24),
			'compression'		=> $this->dword(0),
			'data_size'			=> $this->dword(0),
			'h_resolution'		=> $this->dword(0),
			'v_resolution'		=> $this->dword(0),
			'colors'			=> $this->dword(0),
			'important_colors'	=> $this->dword(0),
		);
	    if ($filename) {
			$fh = fopen($filename, "wb");
			foreach ($header as $h) {
				fwrite($fh, $h);
			}
			// save pixels
			for ($y=$hei-1; $y>=0; $y--) {
				for ($x=0; $x<$wid; $x++) {
					$rgb = imagecolorat($img, $x, $y);
					fwrite($fh, $this->byte3($rgb));
				}
				fwrite($fh, $wid_pad);
			}
			return fclose($fh);
		} else {
			foreach ($header as $h) {
			    echo $h;
			}
			// save pixels
			for ($y = $hei - 1; $y >= 0; $y--) {
				for ($x=0; $x<$wid; $x++) {
					$rgb = imagecolorat($img, $x, $y);
					echo $this->byte3($rgb);
				}
				echo $wid_pad;
			}
			return true;
		}
	}

	private function byte3($n)
	{
		return chr($n & 255) . chr(($n >> 8) & 255) . chr(($n >> 16) & 255);
	}

	private function dword($n)
	{
		return pack("V", $n);
	}

	private function word($n)
	{
		return pack("v", $n);
	}

	/**
	 * imagecreatefrombmp
	 *
	 * @param string $filepath file path
	 *
	 * @return object
	 */
	public function imagecreatefrombmp($filepath)
	{
		// Load the image into a string
		$fh = fopen($filepath, 'rb');
		$read = fread($fh, 10);
		while (!feof($fh) && ($read<>'')) {
			$read .= fread($fh, 1024);
		}
		$temp	= unpack('H*', $read);
		$hex	= $temp[1];
		$header	= substr($hex, 0, 108);
		//	Process the header
		//	Structure: http://www.fastgraph.com/help/bmp_header_format.html
		if (substr($header, 0, 4)=='424d') {
			// Cut it in parts of 2 bytes
			$header_parts = str_split($header, 2);
			// Get the width        4 bytes
			$width	= hexdec($header_parts[19].$header_parts[18]);
			// Get the height        4 bytes
			$height	= hexdec($header_parts[23].$header_parts[22]);
			// Unset the header params
			unset($header_parts);
		}
		// Define starting X and Y
		$x = 0;
		$y = 1;
		// Create newimage
		$image = imagecreatetruecolor($width, $height);
		// Grab the body from the image
		$body = substr($hex, 108);
		// Calculate if padding at the end-line is needed
		// Divided by two to keep overview.
		// 1 byte = 2 HEX-chars
		$body_size		= strlen($body)/2;
		$header_size	= $width * $height;
		// Use end-line padding? Only when needed
		$usePadding		= ( $body_size>($header_size*3)+4 );
		// Using a for-loop with index-calculation instead of str_split to avoid large memory consumption
		// Calculate the next DWORD-position in the body
		for ($i=0; $i<$body_size; $i+=3) {
			//	Calculate line-ending and padding
			if ($x>=$width) {
				// If padding needed, ignore image-padding
				// Shift i to the ending of the current 32-bit-block
				if ($usePadding) {
					$i += $width%4;
				}
				// Reset horizontal position
				$x = 0;
				// Raise the height-position (bottom-up)
				$y++;
				// Reached the image-height? Break the for-loop
				if ($y>$height) {
					break;
				}
			}
			// Calculation of the RGB-pixel (defined as BGR in image-data)
			// Define $i_pos as absolute position in the body
			$i_pos	= $i*2;
			$r		= hexdec($body[$i_pos+4].$body[$i_pos+5]);
			$g		= hexdec($body[$i_pos+2].$body[$i_pos+3]);
			$b		= hexdec($body[$i_pos].$body[$i_pos+1]);
			// Calculate and draw the pixel
			$color	= imagecolorallocate($image, $r, $g, $b);
			imagesetpixel($image, $x, $height-$y, $color);
			// Raise the horizontal position
			$x++;
		}
		// Unset the body / free the memory
		unset($body);
		// Return image-object
		return $image;
	}

	/**
	 * is the file a supported image?
	 *
	 * @param string $filepath filepath
	 *
	 * @return boolean
	 */
	public function isImage($filepath)
	{
		$this->debug->groupCollapsed(__METHOD__, $filepath);
		$isImage = false;
		if (false && function_exists('exif_imagetype')) {
			$type = exif_imagetype($filepath);
			$isImage = ($type !== false && in_array($type, $this->imagetypes));
		} else {
			$imageInfo = getimagesize($filepath);
			$mime = $imageInfo['mime'];
			// $this->debug->log('imageInfo', $imageInfo);
			$isImage = (isset($this->imageTypes[$mime]) && $imageInfo[0] && $imageInfo[1]);
		}
		$this->debug->groupEnd();
		return $isImage;
	}

	/**
	 * get image extension (or typeid)from mime type
	 *
	 * @param string  $mime       mime type
	 * @param boolean $incl_dot   true
	 * @param boolean $ret_typeid false
	 *
	 * @return mixed
	 */
	public function mimeToExt($mime, $incl_dot = true, $ret_typeid = false)
	{
		$this->debug->groupCollapsed(__METHOD__, $mime);
		$ext = '';
		// map to image_type
		$type = isset($this->imageTypes[$mime])
			? $this->imageTypes[$mime]
			: null;
		if ($type) {
			if ($incl_dot) {
				$ext = '.';
			}
			$ext .= image_type_to_extension($type);
			if ($ret_typeid) {
				$ext = $type;
			}
		} else {
			$this->debug->warn('unknown mime type');
		}
		$this->debug->groupEnd();
		return $ext;
	}

	/**
	 * Output image with headers
	 *
	 * @param string $filepath file path
	 * @param array  $info     filename, mime, filesize, etc
	 *
	 * @return void
	 */
	public function passthru($filepath, $info = array())
	{
		$this->debug->group(__METHOD__);
		// $this->debug->log('info', $info);
		$info = array_merge(array(
			'filename'	=> null,
			'md5'		=> null,
			'mime'		=> 'image/jpeg',
			'size'		=> null,
		), $info);
		if (file_exists($filepath)) {
			if (empty($info['md5'])) {
				$info['md5'] = md5_file($filepath);
			}
			if (empty($info['size'])) {
				$info['size'] = filesize($filepath);
			}
			$headers = apache_request_headers();
			$this->debug->log('headers', $headers);
			$this->debug->log('info[md5]', $info['md5']);
			if (isset($headers['If-None-Match']) && $headers['If-None-Match'] == $info['md5']) {
				header('HTTP/1.1 304 Not Modified');
			} else {
				header('Content-Type: '.$info['mime']);
				header('ETag: '.$info['md5']);
				header('Content-Length: '.$info['size']);
				// header('Cache-Control: must-revalidate');
				header('Last Modified: '.gmdate('D, d M Y H:i:s \G\M\T'));
				if ($info['filename']) {
					header('Content-Disposition: inline; filename="'.$info['filename'].'"');
				}
				readfile($filepath);
			}
		} else {
			$this->debug->warn('file does not exist', $filepath);
		}
		$this->debug->groupEnd();
		return;
	}

	/**
	 * [imageSmoothArc description]
	 *
	 * @param resource $img      image resource
	 * @param integer  $cx       Center of ellipse, X-coord
	 * @param integer  $cy       Center of ellipse, Y-coord
	 * @param integer  $w        Width of ellipse ($w >= 2)
	 * @param integer  $h        height of ellipse ($w >= 2)
	 * @param array    $color    Color of ellipse as a four component array with RGBA
	 * @param float    $startDeg Starting angle (degrees) of the arc
	 * @param float    $stopDeg  Stop angle (degrees) of the arc
	 *
	 * @return void
	 * @link   http://www.ulrichmierendorff.com/software/antialiased_arcs.html
	 */
	public static function imageSmoothArc(&$img, $cx, $cy, $w, $h, $color, $startDeg, $stopDeg)
	{
	    $start = deg2rad($startDeg);
	    $stop = deg2rad($stopDeg);
	    while ($start < 0) {
	        $start += 2*M_PI;
	    }
	    while ($stop < 0) {
	        $stop += 2*M_PI;
	    }
	    while ($start > 2*M_PI) {
	        $start -= 2*M_PI;
	    }
	    while ($stop > 2*M_PI) {
	        $stop -= 2*M_PI;
	    }
	    if ($start > $stop) {
	        self::imageSmoothArc($img, $cx, $cy, $w, $h, $color, $start, 2*M_PI);
	        self::imageSmoothArc($img, $cx, $cy, $w, $h, $color, 0, $stop);
	        return;
	    }
	    $a = 1.0 * round($w/2);
	    $b = 1.0 * round($h/2);
	    $cx = 1.0 * round($cx);
	    $cy = 1.0 * round($cy);
	    for ($i=0; $i<4; $i++) {
	        if ($start < ($i+1)*M_PI/2) {
	            if ($start > $i*M_PI/2) {
	                if ($stop > ($i+1)*M_PI/2) {
	                    self::imageSmoothArcDrawSegment($img, $cx, $cy, $a, $b, $color, $start, ($i+1)*M_PI/2, $i);
	                } else {
	                    self::imageSmoothArcDrawSegment($img, $cx, $cy, $a, $b, $color, $start, $stop, $i);
	                    break;
	                }
	            } else {
	                if ($stop > ($i+1)*M_PI/2) {
	                    self::imageSmoothArcDrawSegment($img, $cx, $cy, $a, $b, $color, $i*M_PI/2, ($i+1)*M_PI/2, $i);
	                } else {
	                    self::imageSmoothArcDrawSegment($img, $cx, $cy, $a, $b, $color, $i*M_PI/2, $stop, $i);
	                    break;
	                }
	            }
	        }
	    }
	}

	/**
	 * [imageSmoothArcDrawSegment description]
	 *
	 * @param resource $img   [description]
	 * @param integer  $cx    [description]
	 * @param integer  $cy    [description]
	 * @param float    $a     [description]
	 * @param float    $b     [description]
	 * @param array    $color [description]
	 * @param float    $start [description]
	 * @param float    $stop  [description]
	 * @param integer  $seg   [description]
	 *
	 * @return void
	 */
	protected static function imageSmoothArcDrawSegment(&$img, $cx, $cy, $a, $b, $color, $start, $stop, $seg)
	{
	    // Originally written from scratch by Ulrich Mierendorff, 06/2006
	    // Rewritten and improved, 04/2007, 07/2007
	    // Optimized circle version: 03/2008

	    // Please do not use THIS function directly. Scroll down to imageSmoothArc(...).

	    $fillColor = imageColorExactAlpha($img, $color[0], $color[1], $color[2], $color[3]);
	    switch ($seg) {
	        case 0:
	        	$xp = +1;
	        	$yp = -1;
	        	$xa = 1;
	        	$ya = -1;
	        	break;
	        case 1:
	        	$xp = -1;
	        	$yp = -1;
	        	$xa = 0;
	        	$ya = -1;
	        	break;
	        case 2:
	        	$xp = -1;
	        	$yp = +1;
	        	$xa = 0;
	        	$ya = 0;
	        	break;
	        case 3:
	        	$xp = +1;
	        	$yp = +1;
	        	$xa = 1;
	        	$ya = 0;
	        	break;
	    }
	    for ($x = 0; $x <= $a; $x += 1) {
	        $y = $b * sqrt(1 - ($x*$x)/($a*$a));
	        $error = $y - (int)($y);
	        $y = (int)($y);
	        $diffColor = imageColorExactAlpha($img, $color[0], $color[1], $color[2], 127-(127-$color[3])*$error);
	        imageSetPixel($img, $cx+$xp*$x+$xa, $cy+$yp*($y+1)+$ya, $diffColor);
	        imageLine($img, $cx+$xp*$x+$xa, $cy+$yp*$y+$ya, $cx+$xp*$x+$xa, $cy+$ya, $fillColor);
	    }
	    for ($y = 0; $y < $b; $y += 1) {
	        $x = $a * sqrt(1 - ($y*$y)/($b*$b));
	        $error = $x - (int)($x);
	        $x = (int)($x);
	        $diffColor = imageColorExactAlpha($img, $color[0], $color[1], $color[2], 127-(127-$color[3])*$error);
	        imageSetPixel($img, $cx+$xp*($x+1)+$xa, $cy+$yp*$y+$ya, $diffColor);
	    }
	}

	/**
	 * Textbox
	 *
	 * @param resource $image    image gd resource
	 * @param integer  $fontSize font size
	 * @param integer  $posX     box's top-left x pos
	 * @param integer  $posY     box's top-left y pos
	 * @param integer  $maxW     width of text box
	 * @param integer  $color	 A color identifier created with imagecolorallocate()
	 * @param string   $font     $[name] [<description>]
	 * @param string   $str      The text
	 * @param array    $opts     specify horizontal and vertical alignment
	 *
	 * @return array
	 */
	public static function textBox($image, $fontSize, $posX, $posY, $maxW, $color, $font, $str, $opts = array())
	{
		$debug = \bdk\Debug::getInstance();
		// $debug->groupCollapsed(__FUNCTION__, $str);
		$opts = array_merge(array(
			'horizontal' => 'left',
			'vertical' => 'top',
		), $opts);
		$str = trim($str);
		$words = explode(' ', $str);
		$lines = array(
			array(0,0,0),	// word start word end, line width
		);
		$stats = array(
			'width' =>  0,
			'height' => 0,
			'lines' => array(),
		);
		for ($i=0; $i<count($words); $i++) {
			$line = current($lines);
			$testStr = implode(' ', array_slice($words, $line[0], $i-$line[0]+1));
			// $debug->log('testStr', $testStr);
			$textbox = imageftbbox($fontSize, 0, $font, $testStr); // or die('Error in imagettfbbox function');
			/*
			0 	lower left corner, X position
			1 	lower left corner, Y position
			2 	lower right corner, X position
			3 	lower right corner, Y position
			4 	upper right corner, X position
			5 	upper right corner, Y position
			6 	upper left corner, X position
			7 	upper left corner, Y position
			*/
			$textW = abs($textbox[4] - $textbox[6]);
			if ($textW > $maxW) {
				// $debug->warn('too long');
				$key = key($lines);
				$i--;
				$lines[$key][1] = $i;
				$lines[] = array($i+1, $i+1, $textW);
				end($lines);
			} elseif ($textW > $stats['width']) {
				$stats['width'] = $textW;
			}
		}
		$key = key($lines);
		$lines[$key][1] = $i;
		$lines[$key][2] = $textW;
		$debug->log('lines', $lines);
		foreach ($lines as $line) {
			$lineStr = implode(' ', array_slice($words, $line[0], $line[1]-$line[0]+1));
			$stats['lines'][] = array($lineStr, $line[2]);
		}
		$debug->log('lines', $lines);
		$posY += $fontSize;
		foreach ($stats['lines'] as $line) {
			$posXline = $posX;
			if ($opts['horizontal'] == 'center') {
				$posXline = ($maxW - $line[1]) / 2;
			} elseif ($opts['horizontal'] == 'right') {
				$posXline = $maxW - $line[1];
			}
			imagefttext($image, $fontSize, 0, $posXline, $posY, $color, $font, $line[0]); // or die('Error in imagettftext function');
			$posY += round($fontSize + $fontSize * 0.5);
		}
		$stats['height'] = $posY;
		// $debug->groupEnd();
		return $stats;
	}
}
