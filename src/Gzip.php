<?php

namespace bdk;

/**
 *
 */
class Gzip
{
	/**
	 * Compress a file
	 * Removes original
	 *
	 * @param string  $filepathSrc input file
	 * @param string  $filepathDst optional output file (optional)
	 * @param integer $level       compression level (optional)
	 *
	 * @return string|false
	 */
	public static function compressFile($filepathSrc, $filepathDst = null, $level = 9)
	{
	    if (!isset($filepathDst)) {
	    	$filepathDst = $filepathSrc.'.gz';
	    }
	    $return = $filepathDst;
	    $mode = 'wb'.$level;
	    if ($fpOut = gzopen($filepathDst, $mode)) {
	        if ($fpIn = fopen($filepathSrc, 'rb')) {
	            while (!feof($fpIn)) {
	                gzwrite($fpOut, fread($fpIn, 1024*512));
	            }
	            fclose($fpIn);
	        } else {
	        	$return = false;
	        }
	        gzclose($fpOut);
	    } else {
	    	$return = false;
	    }
    	return $return;
	}

	public static function compressString($string, $level = 9)
	{
		return gzencode($string, $level);
	}

	/**
	 * Uncompress gziped file
	 *
	 * @param string $filepathSrc input file
	 * @param string $filepathDst optional output file (or output directory)
	 *
	 * @return string|false path to uncomperssed file
	 */
	public static function uncompressFile($filepathSrc, $filepathDst = null)
	{
		if (!isset($filepathDst)) {
			$regex = '#^(.+).gz$#';
			$filepathDst = preg_replace($regex, '$1', $filepathSrc);
		} elseif (is_dir($filepathDst)) {
			$pathInfo = pathinfo($filepathSrc);
			$filename = preg_replace($filename, '$1', $pathInfo['filename']);
			$filepathDst = $pathInfo['dirname'].'/'.$filename;
		}
		$return = $filepathDst;
		if ($filepathDst == $filepathSrc) {
			// @todo
			$return = false;
		} elseif ($fpIn = gzopen($filepathSrc, 'rb')) {
			if ($fpOut = fopen($filepathDst, 'wb')) {
				while (!gzeof($fpIn)) {
					fwrite($fpOut, gzread($fpIn, 1024*512));
				}
			} else {
				$return = false;
			}
		} else {
			$return = false;
		}
		return $return;
	}

	public static function decompressString($string)
	{
		return gzdecode($string);
	}
}
