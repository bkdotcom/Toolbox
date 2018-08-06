<?php

namespace bdk;

/**
 *
 */
class Config
{

    protected static $cfgStatic = array();

    /**
     * Magic
     *
     * @param string $name      method name
     * @param array  $arguments arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, array('getCfg','setCfg'))) {
            return call_user_func_array(array($this, $name.'Instance'), $arguments);
        }
    }

    /**
     * Magic
     *
     * @param string $name      method name
     * @param array  $arguments arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (in_array($name, array('getCfg','setCfg'))) {
            return call_user_func_array(array('self', $name.'Static'), $arguments);
        }
    }

    /**
     * Get cfg value
     *
     * @param mixed $path path to config value
     *
     * @return mixed
     */
    protected function getCfgInstance($path = null)
    {
        // $calledClassname = get_called_class();
        $return = null;
        // called from inheriting class
        if (isset($this->cfg)) {
            // $debug->info('getting cfg value');
            $return = ArrayUtil::path($this->cfg, $path);
        } else {
            // $debug->info('getting cfgStatic value');
            $return = ArrayUtil::path(static::$cfgStatic, $path);
        }
        return $return;
    }

    /**
     * Get cfg value
     *
     * @param mixed $path path to config value
     *
     * @return mixed
     */
    protected static function getCfgStatic($path = null)
    {
        $calledClassname = get_called_class();
        if ($calledClassname === __CLASS__) {
            if (!is_array($path)) {
                $path = strlen($path)
                    ? $path = preg_split('#[\./]#', trim($path, '\./'))
                    : array();
            }
            $class = null;
            if (class_exists($path[0])) {
                // $debug->info('class exists', $path[0]);
                $class = array_shift($path);
            } elseif (class_exists('\\'.__NAMESPACE__.'\\'.$path[0])) {
                // $debug->info('class exists 2', $path[0]);
                $class = '\\'.__NAMESPACE__.'\\'.array_shift($path);
            } else {
                // $debug->warn('class does not exist', $path[0]);
            }
            if ($class) {
                $return = call_user_func(array($class, __FUNCTION__), $path);
            }
        } else {
            // called from inheriting class
            $return = ArrayUtil::path(static::$cfgStatic, $path);
        }
        return $return;
    }

    /**
     * Set config value
     *
     * @param string $key   key
     * @param mixed  $value value
     *
     * @return void
     */
    protected function setCfgInstance($key, $value = null)
    {
        if (is_string($key)) {
            $cfg = array();
            ArrayUtil::path($cfg, $key, $value);
        } elseif (is_array($key)) {
            $cfg = $key;
        }
        // called from inheriting class
        if (isset($this->cfg)) {
            // $debug->info('setting cfg');
            $this->cfg = ArrayUtil::mergeDeep($this->cfg, $cfg);
        } else {
            // $debug->info('setting cfgStatic');
            static::$cfgStatic = ArrayUtil::mergeDeep(static::$cfgStatic, $cfg);
        }
    }

    /**
     * Set config value
     *
     * @param string $key   key
     * @param mixed  $value value
     *
     * @return void
     */
    protected static function setCfgStatic($key, $value = null)
    {
        $calledClassname = get_called_class();
        if (is_string($key)) {
            $cfg = array();
            ArrayUtil::path($cfg, $key, $value);
        } elseif (is_array($key)) {
            $cfg = $key;
        }
        if ($calledClassname === __CLASS__) {
            // \bdk\Debug::_info('calling Config::setCfg');
            $class = null;
            foreach ($cfg as $key => $val) {
                if ($key == 'Debug') {
                    \bdk\Debug::getInstance()->setCfg($val);
                    continue;
                }
                if (class_exists($key)) {
                    // $debug->info('class exists', $key);
                    $class = $key;
                } elseif (class_exists('\\'.__NAMESPACE__.'\\'.$key)) {
                    // $debug->info('class exists 2', $key);
                    $class = '\\'.__NAMESPACE__.'\\'.$key;
                }
                if ($class) {
                    call_user_func(array($class, __FUNCTION__), $val);
                }
            }
        } else {
            // called from inheriting class
            static::$cfgStatic = ArrayUtil::mergeDeep(static::$cfgStatic, $cfg);
        }
    }

    /**
     * get configuration value
     *
     * @param string $key name of value we want to retrieve
     *
     * @return mixed
     */
    /*
    public static function get($key)
    {
        $ret = self::$config;
        $path = preg_split('#[\./]#', $key);
        if ($path[0] == 'Debug') {
            $debug = \bdk\Debug::getInstance();
            $ret = $debug->getCfg(implode('/', array_slice($path, 1)));
            $path = array();
        }
        foreach ($path as $k) {
            if (isset($ret[$k])) {
                $ret = $ret[$k];
            } else {
                $ret = null;
                break;
            }
        }
        return $ret;
    }
    */

    /**
     * set one or more config values
     *    set('key', 'value')
     *    set('level1.level2', 'value')
     *    set(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * If setting a single value via method a or b, old value is returned
     *
     * @param string $key   key
     * @param mixed  $value value
     *
     * @return mixed
     */
    /*
    public static function set($key, $value = null)
    {
        $return = null;
        if (is_string($key)) {
            $cfg = array();
            $return = ArrayUtil::path($cfg, $key, $value);
        } elseif (is_array($key)) {
            $cfg = $key;
        }
        if (isset($cfg['Debug'])) {
        	\bdk\Debug::getInstance($cfg['Debug']);
        	unset($cfg['Debug']);
        }
        self::$config = array_replace_recursive(self::$config, $cfg);
        return $return;
    }
    */
}
