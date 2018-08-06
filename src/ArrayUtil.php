<?php

namespace bdk;

/**
 * Array methods
 */
class ArrayUtil
{

    private static $temp;
    private static $isRecursive;

    /**
     * Go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array
     *
     * @return array
     */
    public static function columnKeys($rows)
    {
        $debug = \bdk\Debug::getInstance();
        return $debug->methodTable->colKeys($rows);
    }

    /**
     * (AR)ray (F)ield Sort.
     * Sort a multi-dimensional array according to a list of fields.
     * preserves keys
     *
     * @param array        $array  array to sort
     * @param array|string $fields field(s) to sort by
     *
     * @return array
     * @uses   fieldSortFunc
     */
    public static function fieldSort($array, $fields)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        self::$temp = $fields;
        $i = 0;
        // restructure the array for sorting.
        //   PHP's uasort is not a "stable sort" (original order not maintained if rows are equal)
        //   if sort values are equal, will sort by temp increment value
        foreach ($array as $k => $row) {
            $array[$k] = array($i++, $row);
        }
        uasort($array, array(__CLASS__, 'fieldSortFunc'));
        foreach ($array as $k => $row) {
            $array[$k] = $row[1];
        }
        self::$temp = null; // unset($this->temp);
        return $array;
    }

    /**
     * Internal sorting function for fieldSort()
     *
     * @param array $oa1 array 1
     * @param array $oa2 array 2
     *
     * @return   integer
     * @internal
     */
    protected static function fieldSortFunc($oa1, $oa2)
    {
        $count = count(self::$temp);
        for ($i=0; $i<$count; $i++) {
            $f = self::$temp[$i];
            $fNext = isset(self::$temp[$i+1])
                ? self::$temp[$i+1]
                : null;
            $a1 = $oa1[1];
            $a2 = $oa2[1];
            if (!in_array($f, array('ASC','DESC'), true)) {
                // DESC & ASC will be skipped
                $s1 = null;
                if (is_array($a1) && isset($a1[$f])) {
                    $s1 = $a1[$f];
                } elseif (is_object($a1)) {
                    $s1 = $a1->$f;
                }
                $s2 = null;
                if (is_array($a2) && isset($a2[$f])) {
                    $s2 = $a2[$f];
                } elseif (is_object($a2)) {
                    $s2 = $a2->$f;
                }
                $cmp = strnatcasecmp($s1, $s2);
                if ($fNext == 'DESC') {
                    $cmp *= -1;
                }
                if ($cmp != 0) {
                    return $cmp;
                }
            }
        }
        // rows are equal
        // keep original order
        return $oa1[0] < $oa2[0] ? -1 : 1;
    }

    /**
     * for an associative array, return all the values for given column name
     * see also uniqueCol
     *
     * @param array  $rows    the array
     * @param string $colName the name of the "column" to get
     *
     * @return array
     */
    public static function columnVals($rows, $colName)
    {
        $return = array();
        foreach ($rows as $row) {
            $return[] = isset($row[$colName])
                ? $row[$colName]
                : null;
        }
        return $return;
    }

    /**
     * Implode array ala CSV
     *
     * @param array  $values values to implode
     * @param string $char   = ','
     *
     * @return string
     */
    public static function implodeDelim($values, $char = ',')
    {
        if (is_array($char)) {  // params were originally swapped
            list($values, $char) = array($char, $values);
        }
        if (empty($values)) {
            return '';
        }
        $str = '';
        foreach ($values as $k => $v) {
            if ($char == ',') {
                $v = str_replace('"', '""', $v);
            }
            if (preg_match('/['.$char.'\n"]/', $v)) {
                $v = '"'.$v.'"';
            }
            $values[$k] = $v;
        }
        $str = implode($char, $values);
        return $str;
    }

    /**
     * returns true if array is not empty and there is a non-int key
     *
     * @param array $array array to test
     *
     * @return boolean
     */
    public static function isHash($array)
    {
        // is_array($data) && ( empty($data) || array_keys($data) === range(0, count($data)-1) );
        $hash = false;
        if (is_array($array) && !empty($array)) {
            $keys = array_keys($array);
            foreach ($keys as $k) {
                if (!is_int($k)) {
                    $hash = true;
                    break;
                }
            }
        }
        return $hash;
    }

    /**
     * Rename a key while maintaining position
     *
     * @param array  $array array to update
     * @param string $from  existing key
     * @param string $to    new key
     *
     * @return void
     */
    public static function keyRename(&$array, $from, $to)
    {
        if (is_array($array) && $from !== $to) {
            $keys = array_keys($array);
            $pos = array_search($from, $keys, true);
            if ($pos !== false) {
                $next_key = isset($keys[$pos+1])
                    ? $keys[$pos+1]
                    : null;
                $value = $array[$from];
                unset($array[$from]);
                self::keySplice($array, $next_key, 0, array($to=>$value));
            }
        }
        return;
    }

    /**
     * http://www.php.net/manual/en/function.array-splice.php
     * 26-May-2002 07:02
     *
     * @param array   $input       input array
     * @param string  $key_ofs     key of offset
     * @param integer $length      number of values to remove at offset position
     * @param mixed   $replacement values to insert
     *
     * @return array
     * @author paule at cs dot tamu dot edu
     */
    public static function keySplice(&$input, $key_ofs, $length = null, $replacement = '%_null_%')
    {
        // Adjust the length if it was negative or not passed
        $ret_array = array();
        $new_array = array();
        $key_found = false;
        if (is_string($key_ofs) && preg_match('/^(?!0)\d+$/', $key_ofs)) {
            $key_ofs = (int) $key_ofs;
        }
        $count = is_int($length)
            ? $length
            : 0;
        #$this->debug->log('key_ofs', $key_ofs);
        if (is_array($input)) {
            // Cycle through input
            foreach ($input as $k => $v) {
                #$this->debug->log('k', $k);
                if (!$key_found) {
                    #$this->debug->log('&nbsp; hasn\'t been found');
                    if ($k === $key_ofs) {
                        #$this->debug->info(' found');
                        $key_found = true;
                        if (is_array($replacement)) {
                            foreach ($replacement as $k_r => $v_r) {
                                $new_array[$k_r] = $v_r;
                            }
                        } elseif ($replacement !== '%_null_%') {
                            $new_array[] = $replacement;
                        }
                    } else {
                        $new_array[$k] = $v;
                    }
                }
                if ($key_found) {
                    #$this->debug->log('&nbsp; has been found');
                    if ($count > 0) {
                        #$this->debug->log(' skipping '.$k);
                        $ret_array[$k] = $v;
                        $count--;
                    } else {
                        if (is_int($k) && array_key_exists($k, $new_array)) {
                            $new_array[] = $v;
                        } else {
                            $new_array[$k] = $v;
                        }
                    }
                }
            }
            if (!$key_found) {
                #$this->debug->warn('never found insert key');
                if (is_array($replacement)) {
                    foreach ($replacement as $k_r => $v_r) {
                        $new_array[$k_r] = $v_r;
                    }
                } elseif ($replacement !== '%_null_%') {
                    $new_array[] = $replacement;
                }
            }
            // Finish up
            #$this->debug->log('input length changed by : '.(count($new_array)-count($input)));
            $input = $new_array;
        }
        #$this->debug->log('new_array', $new_array);
        #$this->debug->log('ret_array', $ret_array);
        return $ret_array;
    }

    /**
     * Apply function to array values
     *
     * @param string $function callback function
     * @param mixed  $a_or_v   array or value
     * @param array  $params   optional paramaters to pass to callback
     * @param array  $opts     options
     * @param array  $hist     @internal
     *
     * @return type
     */
    public static function mapDeep($function, $a_or_v, $params = array(), $opts = array(), &$hist = array())
    {
        $opts = array_merge(array(
            'callback_expects'  => 'scalar',    // 'scalar', 'array', 'both'
            'resource'  => false,               // whether or not to map or skip the type: resource
            'depth'     => 0,                   // @interal
        ), $opts);
        $callback_params = $params;
        if (!is_array($callback_params)) {
            $callback_params = !is_null($callback_params)
                ? array($callback_params)
                : array();
        }
        $callback_params = array_merge(array($a_or_v), $callback_params);
        if (is_array($a_or_v)) {
            if (!$opts['depth']) {
                // check if we need to be on the look out for self-reference loop
                $str = print_r($a_or_v, true);
                // $this->isRecursive = $this->debug->isRecursive($a_or_v);
                self::$isRecursive = strpos($str, '*RECURSION*') !== false;
            }
            $opts['depth']++;
            if (in_array($opts['callback_expects'], array('array','both'))) {
                $a_or_v = call_user_func_array($function, $callback_params);
            }
            $hist[] = $a_or_v;
            foreach ($a_or_v as $k => $v) {
                if (self::$isRecursive && in_array($v, $hist)) {
                    continue;   // recursion
                }
                // if (!$this->isRecursive || !$this->debug->isRecursive($v, $k)) {
                // }
                $a_or_v[$k] = self::mapDeep($function, $v, $params, $opts, $hist);
            }
        } elseif (is_resource($a_or_v) && !$opts['resource']) {
            #$this->debug->log('resource encountered', $a_or_v);
        } elseif (in_array($opts['callback_expects'], array('scalar','both'))) {
            $a_or_v = call_user_func_array($function, $callback_params);
        }
        return $a_or_v;
    }

    /**
     * Similar to array_merge_recursive but keyed-valued are always overwritten.
     * Priority goes to the 2nd array.
     * great for applying default values
     * http://us2.php.net/manual/en/function.array-merge-recursive.php
     *
     * @param array $aDefault default array
     * @param array $a2       array 2
     * @param opts  $opts     options
     *
     * @return array
     */
    public static function mergeDeep($aDefault, $a2, $opts = array())
    {
        if (empty($opts['prepped'])) {
            $opts = array_merge(array(
                'empty_overwrites'  => true,
                'int_keys'          => 'unique',    // overwrite (or replace) | unique | append
                                                    // unique & append reindex!
                // internal use only
                'prepped'           => true,
            ), $opts);
        }
        if (!isset($a2) || $a2 === '') {
            $aDefault = $opts['empty_overwrites']
                ? $a2
                : $aDefault;
        } elseif (!is_array($aDefault) || !is_array($a2)) {
            $aDefault = $a2;
        } else {
            foreach ($a2 as $k2 => $v2) {
                if (is_int($k2) && $opts['int_keys'] == 'unique') {
                    if (!in_array($v2, $aDefault)) {
                        #$this->debug->log('int_key & val does not already exist');
                        $aDefault[] = $v2;
                    }
                } elseif (is_int($k2) && $opts['int_keys'] == 'append') {
                    $aDefault[] = $v2;
                } else {
                    if (!isset($aDefault[$k2])) {
                        $aDefault[$k2] = $v2;
                    } elseif ($opts['empty_overwrites'] && !is_array($v2)) {    // null gets overwritten
                        $aDefault[$k2] = $v2;
                    } else {
                        $aDefault[$k2] = self::mergeDeep($aDefault[$k2], $v2, $opts);
                    }
                }
            }
        }
        return $aDefault;
    }

    /**
     * [array_merge_recursive2 description]
     *
     * @param array ...$arrays [description]
     *
     * @return [type] [description]
     */
    /*
    public static function mergeDeep2(...$arrays)
    {
        $merged = array_shift($arrays);
        while ($arrays) {
            $array = array_shift($arrays);
            foreach ($array as $key => $value) {
                if (is_string($key)) {
                    if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                        $merged[$key] = self::mergeDeep2($merged[$key], $value);
                    } else {
                        $merged[$key] = $value;
                    }
                } else {
                    $merged[] = $value;
                }
            }
        }
        return $merged;
    }
    */

    /**
     * Convert object to array
     *
     * @param mixed   $var           object to convert to array
     * @param boolean $lowerCaseKeys should strtolower be applied to array's keys
     *
     * @return mixed
     */
    public static function objToArray($var, $lowerCaseKeys = true)
    {
        if (is_object($var)) {
            $var = get_object_vars($var);
        }
        if (is_array($var)) {
            if ($lowerCaseKeys) {
                $var = array_change_key_case($var);
            }
            foreach ($var as $k => $v) {
                $var[$k] = self::objToArray($v, $lowerCaseKeys);
            }
        }
        if (is_string($var)) {
            $var = trim($var);
        }
        return $var;
    }

    /**
     * Get or set value of array at given path
     *
     * @param array        $array array to pull/get value from
     * @param array|string $path  path of the value
     * @param mixed        $value optional new value
     *
     * @return mixed pointer to path, or, if setting value, former value
     */
    public static function path(&$array, $path = array(), $value = '>-null-<')
    {
        return $value !== '>-null-<'
            ? self::pathSet($array, $path, $value)
            : self::pathGet($array, $path);
    }

    /**
     * Get value from array
     *
     * @param array|string $path  key path
     * @param array        $array array to navigate
     *
     * @return mixed
     */
    public static function pathGet($array, $path)
    {
        return \bdk\Debug\Utilities::arrayPathGet($array, $path);
    }

    /**
     * Set array value
     *
     * @param array|string $path  key path
     * @param array        $array array to navigate
     * @param mixed        $value new value
     *
     * @return mixed previous value
     */
    public static function pathSet(&$array, $path, $value = null)
    {
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $found = true;
        $cur = &$array;
        $path = \array_reverse($path);
        while ($path) {
            $key = \array_pop($path);
            if (!\is_array($cur)) {
                $cur = array();
            }
            if (isset($cur[$key])) {
                $cur = &$cur[$key];
            } elseif ($key == '__end__') {
                $keys = \array_keys($cur);
                $path[] = \array_pop($keys);
            } elseif ($key == '__push__') {
                $cur[] = array();
                $keys = \array_keys($cur);
                $path[] = \array_pop($keys);
            } elseif ($key == '__reset__') {
                $keys = \array_keys($cur);
                $path[] = \array_shift($keys);
            } else {
                $found = false;
                $cur[$key] = array();
                $cur = &$cur[$key];
            }
        }
        $return = $found
            ? $cur
            : null;
        $cur = $value;
        return $return;
    }

    /**
     * remove value from array / does not reindex
     *
     * @param array   $haystack array
     * @param mixed   $needle   value to remove from array
     * @param boolean $strict   true
     *
     * @return integer removed count
     */
    public static function removeVal(&$haystack, $needle, $strict = true)
    {
        $count = 0;
        if (is_array($haystack)) {
            // PHP 4 doesn't have 3rd array_keys() param -> perform strict check in loop
            $keys = array_keys($haystack, $needle);
            foreach ($keys as $k) {
                if ($strict && $haystack[$k] !== $needle) {
                    continue;
                }
                unset($haystack[$k]);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Find "rows" containing values
     *
     * @param array $array  array to search
     * @param array $values values to search for
     * @param mixed $opts   optional array of options or boolean
     *
     * @return type
     */
    public static function searchFields($array, $values, $opts = array())
    {
        if (!is_array($array)) {
            $array = array($array);
        }
        if (!is_array($opts)) {
            if (is_bool($opts)) {
                $opts = array(
                    'whole' => $opts,
                    'case'  => $opts,
                );
            } else {
                $opts = array();
            }
        }
        $opts = array_merge(array(
            'whole' => false,
            'case' => false,
        ), $opts);
        foreach ($values as $colname => $search_value) {
            if ($search_value === null || $search_value === '') {
                unset($values[$colname]);
            } elseif (is_array($search_value)) {
                $values[$colname] = array_shift($search_value);
            }
        }
        foreach ($values as $colname => $search_value) {
            #$this->debug->log($colname.' = '.$search_value);
            if (is_string($search_value)) {
                if (!$opts['case']) {
                    $search_value = strtolower($search_value);
                }
                $search_value = trim($search_value);
            }
            foreach ($array as $key => $row) {
                $col_value = isset($row[$colname])
                    ? $row[$colname]
                    : null;
                if (is_string($col_value)) {
                    if (!$opts['case']) {
                        $col_value = strtolower($col_value);
                    }
                    $col_value = trim($col_value);
                }
                if (is_bool($search_value)) {
                    $found = $col_value == $search_value;
                } elseif ($opts['whole']) {
                    $found = $col_value == $search_value;
                } else {
                    $found = strpos($col_value, (string) $search_value) !== false;
                }
                if (!$found) {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }

    /**
     * Recursively search for key in array
     * Priority is givent to upper levels
     * Returns first match
     *
     * @param array $array  haystack
     * @param array $needle needle/key
     *
     * @return mixed
     */
    public static function searchKey($array, $needle)
    {
        $return = false;
        if (!is_array($array)) {
            $array = array();
        }
        $array_keys = array();
        foreach ($array as $k => $v) {
            if ($k == $needle) {
                $return = $v;
                break;
            }
            if (is_array($v)) {
                // don't search deeper levels until finished with this level
                $array_keys[] = $k;
            }
        }
        if ($return === false) {
            foreach ($array_keys as $k) {
                if (($result = self::searchKey($array[$k], $needle)) !== false) {
                    $return = $result;
                    break;
                }
            }
        }
        return $return;
    }

    /**
     * returns all the unique values for columns (fields)
     *
     * @param array        $array       array
     * @param array|string $cols_to_get columns desired
     * @param boolean      $assoc       when only returning one col, should each row be an assoc/hash array?
     *
     * @return array
     */
    public static function uniqueCol($array, $cols_to_get, $assoc = true)
    {
        if (!is_array($array)) {
            $array = array();
        }
        if (!is_array($cols_to_get)) {
            $cols_to_get = array($cols_to_get);
        }
        $col_values_temp    = array();
        $col_values_strings = array();
        $col_values  = array();
        foreach (array_keys($array) as $i) {
            $line_values = array();
            foreach ($cols_to_get as $colname) {
                if (isset($array[$i][$colname])) {
                    $line_values[$colname] = $array[$i][$colname];
                }
            }
            if (!empty($line_values)) {
                $line_string = implode('', array_values($line_values));
                array_push($col_values_temp, $line_values);
                array_push($col_values_strings, $line_string);
            }
        }
        #$this->debug->log('col_values_temp', $col_values_temp);
        #$this->debug->log('col_values_strings', $col_values_strings);
        #$this->debug->log('col_values', $col_values);
        $keys = array_keys(array_unique($col_values_strings));      // $keys are the line-#s
        foreach ($keys as $key) {
            array_push($col_values, $col_values_temp[$key]);
        }
        if (count($cols_to_get) == 1) {
            sort($col_values);
            if (!$assoc) {
                foreach ($col_values as $i => $a) {
                    $col_values[$i] = $a[ $cols_to_get[0] ];
                }
            }
        } else {
            $col_values = self::fieldSort($col_values, $cols_to_get);
        }
        #$this->debug->log($col_values, 'col_values');
        return $col_values;
    }
}
