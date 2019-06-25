<?php

namespace bdk;

use ArrayIterator;

/**
 * Session wrapper
 *
 * This class provides
 *   * a OOP means of accessing $_SESSION
 *   * single method to set all the various session related settings
 */
class Session implements \ArrayAccess, \Countable, \IteratorAggregate
{

    protected $cfg = array(
        'collectionName' => null,
    );

    protected static $cfgStatic = array(
        'cookieLifetime'=> 0,                   // TTL (seconds) 0 = expire when close browser
        'cookiePath'    => '/',
        'cookieDomain'  => null,
        'cookieSecure'  => false,
        'cookieHttponly'=> false,
        'name'          => null,
        'id'            => null,                // session_id()...  typically ineffective when use_strict_mode
                                                //     we'll disable use_strict_mode
        'regenOnStart'  => false,               // regenerate session id after session_start
        'useStrictMode' => true,                // do not accecpt unititialized session id
        'useCookies'    => true,
        'useOnlyCookies'=> true,
        /*
            These options get their default from default php setting
        */
        'cacheExpire'   => 180,                 // int (minutes) session_cache_expire()
                                                //   only applies when cacheLimiter != 'nocache'
        'cacheLimiter'  => 'nocache',           // session_cache_limiter()
                                                //   public | private_no_expire | private | nocache
        'gcMaxlifetime' => 1440,                // 'session.gc_maxlifetime'
        'gcProbability' => 1,                   // 'session.gc_probability'
        'gcDivisor'     => 1000,                // 'session.gc_divisor'
        'savePath'      => '',                  // 'session.save_path' || sys_get_temp_dir()
    );

    protected static $cfgExplicitlySet = array();   // make sure we don't overwrite cfg with defaults

    protected static $status = array(
        'requestId' => null,
        'regenerated' => false,
    );

    private static $instance;

    /**
     * Constructor
     *
     * @param array $cfg Configuration
     */
    public function __construct(array $cfg = array())
    {
        if (!isset(self::$instance)) {
            // self::getInstance() will always return initial/first instance
            self::$instance = $this;
        }
        $this->setCfg($cfg);
        if (\ini_get('session.save_handler') == 'files') {
            \bdk\Debug::_info('session savePath', self::$cfgStatic['savePath']);
        }
    }

    /**
     * Remove all items from collection
     *
     * @return void
     */
    public static function clear()
    {
        self::start();
        foreach (\array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Destroys all data registered to session
     * Removes session cookie
     *
     * @return boolean
     */
    public static function destroy()
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        foreach (\array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
        self::$cfgStatic['id'] = null;
        $success = \session_destroy();
        if (!$success) {
            return false;
        }
        /*
            Clear cookie so new session not automatically started on next page load
            If strict mode is enabled... this isn't exactly necessary...  a new id will be used..
            however, we may not want to start a new session
        */
        \setcookie(
            self::$cfgStatic['name'],
            '',
            \time()-60*60*24,
            self::$cfgStatic['cookiePath'],
            self::$cfgStatic['cookieDomain'],
            self::$cfgStatic['cookieSecure'],
            self::$cfgStatic['cookieHttponly']
        );
        return $success;
    }

    /**
     * Perform session data garbage collection
     *
     * @return integer number of deleted sessions
     */
    public static function gc()
    {
        if (\version_compare(PHP_VERSION, '7.0.0', '>=')) {
            return \session_gc();
        }
        /*
            Polyfill for PHP < 7
        */
        $initial = array(
            'status' => \session_status(),
            'probability' => \ini_get('session.gc_probability'),
            'divisor' => \ini_get('session.gc_divisor'),
        );
        if ($initial['status'] === PHP_SESSION_DISABLED) {
            return false;
        }
        if ($initial['status'] === PHP_SESSION_ACTIVE) {
            // garbage collection is performed on session_start..
            // close our open session... and restart it
            \session_write_close();
        }
        \ini_set('session.gc_probability', 1);
        \ini_set('session.gc_divisor', 1);
        \session_start();
        \ini_set('session.gc_probability', $initial['probability']);
        \ini_set('session.gc_divisor', $initial['divisor']);
        if ($initial['status'] === PHP_SESSION_NONE) {
            if (self::$status['requestId']) {
                \session_write_close();
            } else {
                \session_destroy();
            }
        }
        return true;
    }

    /**
     * Get session value
     *
     * Essentially just a wrapper for
     *    start session (if not started)
     *    $_SESSION[$key]
     *
     * @param string $key session key
     *
     * @return mixed The key's value, or the default value
     */
    public static function &get($key)
    {
        self::startIf();
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            $val = null;
            return $val;
        }
    }

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @param array $cfg optional config
     *
     * @return object
     */
    public static function getInstance($cfg = array())
    {
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            // self::$instance set in __construct
            new $className($cfg);
        } elseif ($cfg) {
            self::$instance->setCfg($cfg);
        }
        return self::$instance;
    }

    /**
     * Does this collection have a given key?
     *
     * @param string $key The data key
     *
     * @return boolean
     */
    public static function has($key)
    {
        self::startIf();
        return \array_key_exists($key, $_SESSION);
    }

    /**
     * Assign new session id
     *
     * @param boolean $delOldSession invalidate existing id?
     *
     * @return boolean
     */
    public static function regenerateId($delOldSession = false)
    {
        $success = false;
        if (self::$status['regenerated']) {
            // already regenerated... no sense in doing it again
            return false;
        }
        if (empty(self::$status['requestId'])) {
            // no session id was passed... there's no need to regenerate!
            return false;
        }
        if (\session_status() === PHP_SESSION_NONE) {
            // there's currently not a session
            self::$cfgStatic['regenOnStart'] = true;
        }
        if (\session_status() === PHP_SESSION_ACTIVE) {
            $success = \session_regenerate_id($delOldSession);
            self::$cfgStatic['regenOnStart'] = false;
            self::$status['regenerated'] = true;
        }
        if ($success) {
            \bdk\Debug::_info('new session_id', \session_id());
        }
        return $success;
    }

    /**
     * Remove item from collection
     *
     * @param string $key The data key
     *
     * @return void
     */
    public static function remove($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Set value
     *
     * @param string $key   The data key
     * @param mixed  $value The data value
     *
     * @return void
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public function setCfg($key, $value = null)
    {
        if (\is_string($key)) {
            $cfg = array();
            ArrayUtil::path($cfg, $key, $value);
        } elseif (\is_array($key)) {
            $cfg = $key;
        }
        $this->cfg = \array_merge($this->cfg, \array_intersect_key($cfg, $this->cfg));
        self::setCfgStatic(\array_intersect_key($cfg, self::$cfgStatic));
    }

    public static function setCfgStatic($key, $value = null)
    {
        if (\is_string($key)) {
            $cfg = array();
            ArrayUtil::path($cfg, $key, $value);
        } elseif (\is_array($key)) {
            $cfg = $key;
        }
        self::$cfgExplicitlySet = \array_merge(self::$cfgExplicitlySet, $cfg);
        self::$cfgStatic = \array_merge(self::$cfgStatic, self::cfgDefaults(), $cfg);
        self::cfgApplyStatic();
    }

    /**
     * Start session if it's not already started
     *
     * @return boolean
     */
    public static function start()
    {
        /*
            session_id() will continue to return id even after session_write_close()
        */
        if (\session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (\headers_sent()) {
            return false;
        }
        self::setCfgStatic(array());  // makes sure all default's determined and set
        $success = \session_start();
        \bdk\Debug::_info('started', \session_id());
        if (self::$cfgStatic['regenOnStart']) {
            self::regenerateId();
        }
        return $success;
    }

    /**
     * Start session only if we have session request param
     *
     * @return boolean
     */
    public static function startIf()
    {
        self::setCfgStatic(array());
        if (self::$status['requestId']) {
            return self::start();
        } else {
            return false;
        }
    }

    /**
     * Apply configuration settings
     *
     * @return void
     */
    private static function cfgApplyStatic()
    {
        $iniMap = array(
            'gcMaxlifetime' => 'session.gc_maxlifetime',
            'gcProbability' => 'session.gc_probability',
            'gcDivisor'     => 'session.gc_divisor',
            'savePath'      => 'session.save_path',
            'useCookies'    => 'session.use_cookies',
            'useOnlyCookies'=> 'session.use_only_cookies',
        );
        foreach ($iniMap as $cfgKey => $iniKey) {
            \ini_set($iniKey, self::$cfgStatic[$cfgKey]);
        }
        \session_set_cookie_params(
            self::$cfgStatic['cookieLifetime'],
            self::$cfgStatic['cookiePath'],
            self::$cfgStatic['cookieDomain'],
            self::$cfgStatic['cookieSecure'],
            self::$cfgStatic['cookieHttponly'] // php >= 5.2.0
        );
        if (\ini_get('session.save_handler') == 'files' && !\is_dir(self::$cfgStatic['savePath'])) {
            \bdk\Debug::_warn('savePath doesn\'t exist -> creating');
            \mkdir(self::$cfgStatic['savePath'], 0700, true);
        }
        \session_cache_expire(self::$cfgStatic['cacheExpire']);
        \session_cache_limiter(self::$cfgStatic['cacheLimiter']);
        if (self::$cfgStatic['name'] != \session_name()) {
            \session_name(self::$cfgStatic['name']);
        }
        if (self::$cfgStatic['id']) {
            \bdk\Debug::_info('session_id passed : setting useStrictMode to false');
            \ini_set('session.use_strict_mode', false);
            \session_id(self::$cfgStatic['id']);
        } else {
            \ini_set('session.use_strict_mode', self::$cfgStatic['useStrictMode']);
        }
    }

    /**
     * Determine session name
     *
     * depending on useCookies and useOnlyCookies options:
     * if "PHPSESSID" param passed as COOKIE/GET then "PHPSESSID" will be used
     * Otherwise, SESSIONID will be used  (ie preference is SESSIONID)
     *
     * @return string
     */
    private static function cfgDefaultName()
    {
        if (isset(self::$cfgExplicitlySet['name'])) {
            return self::$cfgExplicitlySet['name'];
        }
        $cfg = \array_merge(self::$cfgStatic, self::$cfgExplicitlySet);
        $nameDefault = null;
        $sessionNamePref = array('SESSIONID', \session_name());
        foreach ($sessionNamePref as $nameTest) {
            if ($cfg['useCookies'] && !empty($_COOKIE[$nameTest])) {
                $nameDefault = $nameTest;
                self::$status['requestId'] = $_COOKIE[$nameTest];
                break;
            }
            if (!$cfg['useOnlyCookies'] && !empty($_GET[$nameTest])) {
                $nameDefault = $nameTest;
                self::$status['requestId'] = $_GET[$nameTest];
                break;
            }
        }
        if (!$nameDefault) {
            $nameDefault = $sessionNamePref[0];
        }
        return $nameDefault;
    }

    /**
     * Get default config values
     *
     * Only returns values that haven't been explicitly set already
     *
     * @return array
     */
    private static function cfgDefaults()
    {
        $defaults = array(
            'cacheExpire'   => \session_cache_expire(),     // int (minutes) session_cache_expire()
                                                            //   only applies when cacheLimiter != 'nocache'
            'cacheLimiter'  => \session_cache_limiter(),    // session_cache_limiter()
                                                            //   public | private_no_expire | private | nocache
            'gcMaxlifetime' => (int) \ini_get('session.gc_maxlifetime'),
            'gcProbability' => (int) \ini_get('session.gc_probability'),
            'gcDivisor'     => (int) \ini_get('session.gc_divisor'),
            'savePath'      => \session_save_path() ?: \sys_get_temp_dir(),
            'name'          => self::cfgDefaultName(),
        );
        /*
        if (empty($defaults['savePath']) && \ini_get('session.save_handler') == 'files') {
            $defaults['savePath'] = \sys_get_temp_dir();
        }
        */
        return \array_diff_key($defaults, self::$cfgExplicitlySet);
    }

    /*
        ArrayAccess interface
    */

    /**
     * Does session have given key?
     *
     * @param string $key The data key
     *
     * @return boolean
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * Get collection item for key
     *
     * @param string $key The data key
     *
     * @return mixed The key's value, or the default value
     */
    public function &offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Set collection item
     *
     * @param string $key   The data key
     * @param mixed  $value The data value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Remove item from collection
     *
     * @param string $key The data key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->remove($key);
    }

    /*
        Countable interface
    */

    /**
     * Get number of items in collection
     *
     * @return integer
     */
    public function count()
    {
        $this->start();
        return \count($_SESSION);
    }

    /*
        IteratorAggregate interface
    */

    /**
     * Get collection iterator
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        $this->start();
        return new ArrayIterator($_SESSION);
    }
}
