<?php

namespace bdk;

use bdk\Debug;
use bdk\Config;

/**
 * PHP methods
 */
class Php extends Config
{

    protected static $cfgStatic = array(
        'keepQueryDot' => true,               // PHP turns some.var=value into 'some_var' => 'value' - setting true preserves the dot
        // 'debugInfo' => true,
    );

    /**
     * Check and normalize various PHP settings/values
     *
     * @param array $cfg (optional) configuration
     *
     * @return void
     */
    public static function bootstrap($cfg = array())
    {
        self::normalizeServerVar();
        $debug = Debug::getInstance();
        $hasLog = $debug->internal->hasLog();
        $collectWas = $debug->setCfg('collect', true);
        self::setCfg($cfg);
        self::checkSettings();
        self::checkRequestVars();
        $debug->setCfg('collect', $collectWas);
        if (!$hasLog) {
            $entryCount = $debug->getData('entryCount');
            $debug->setData('entryCountInitial', $entryCount);
        }
    }

    /**
     * Prep request parameters
     *
     * @return void
     */
    public static function checkRequestVars()
    {
        if (version_compare(PHP_VERSION, '6.0.0', '<')) {
            if (ini_get('register_globals')) {
                Debug::_warn('<a target="_blank" href="http://www.php.net/manual/en/security.globals.php">register_globals</a> is on,  strongly recommended to turn off');
                foreach (array_keys($_REQUEST) as $k) {
                    if (isset($GLOBALS[$k]) && $GLOBALS[$k] === $_REQUEST[$k]) {
                        unset($GLOBALS[$k]);
                    }
                }
            }
            if (get_magic_quotes_gpc()
                || ini_get('magic_quotes_sybase') && strtolower(ini_get('magic_quotes_sybase')) != 'off'
            ) {
                Debug::_warn('<a target="_blank" href="http://www.php.net/manual/en/security.magicquotes.php">magic_quotes</a> is on,  strongly recommended to turn off');
                $_GET       = ArrayUtil::mapDeep('stripslashes', $_GET);
                $_POST      = ArrayUtil::mapDeep('stripslashes', $_POST);
                $_COOKIE    = ArrayUtil::mapDeep('stripslashes', $_COOKIE);
                // @todo _REQUEST
            }
        }
        if (self::$cfgStatic['keepQueryDot']) {
            $_GET = Str::parse($_SERVER['QUERY_STRING']);   // allow "." in keys
        }
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
            Debug::getInstance()->info('JSON POST data');
            $postdata = file_get_contents('php://input');
            $_POST = json_decode($postdata, true);
            $_REQUEST = array_merge($_REQUEST, $_POST);
        }
    }

    /**
     * Check PHP settings
     *
     * @return void
     */
    public static function checkSettings()
    {
        if (version_compare(PHP_VERSION, '6.0.0', '<') && ini_get('safe_mode')) {
            Debug::_warn(
                '<a target="_blank" href="http://www.php.net/manual/en/features.safe-mode.php">safe_mode</a> is on'
                .( ini_get('safe_mode_gid') ? ' / safe_mode_gid = '.ini_get('safe_mode_gid') : '' )
            );
        }
        if (ini_get('default_charset') == '') {
            Debug::_info('default_charset not set&hellip; setting to UTF-8');
            // this will also set default Content-Type header to 'Content-Type: text/html; charset=UTF-8'
            ini_set('default_charset', 'UTF-8');
        }
        if (!extension_loaded('mbstring')) {
            Debug::_warn('mbstring extension not loaded');
        } elseif (ini_get('mb_string.internal_encoding') == '') {
            Debug::_info('mb_string.internal_encoding not set&hellip;');
            $defaultCharset = ini_get('default_charset');
            Debug::_info(' setting to default_charset: '.$defaultCharset);
            mb_internal_encoding($defaultCharset);
            mb_regex_encoding($defaultCharset);
        }
    }

    /**
     * Output environment info
     *
     * @return void
     */
    /*
    public static function debugInfo()
    {
        $debug = Debug::getInstance();
        $debug->group('environment');
        $debug->groupUncollapse();
        $debug->info('REQUEST_TIME', date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']));
        foreach (array('HTTP_HOST','REQUEST_URI','SERVER_NAME','SERVER_ADDR','REMOTE_ADDR') as $k) {
            $debug->info($k, $_SERVER[$k]);
        }
        $debug->info('PHP Version', PHP_VERSION);
        $debug->info('memory_limit', self::getMemoryLimit());
        $debug->info('cache_limiter', ini_get('session.cache_limiter'));
        if (!empty($_COOKIE)) {
            $debug->info('$_COOKIE', $_COOKIE);
        }
        if (!empty($_POST)) {
            $debug->info('$_POST', $_POST);
        }
        if (!empty($_FILES)) {
            $debug->info('$_FILES', $_FILES);
        }
        $debug->groupEnd();
    }
    */

    /**
     * Returns the calling function
     *
     * @param boolean $retInfo (true): return info array, false: return only function name
     * @param integer $offset  how many fuctions to go back (default = 0)
     *
     * @return string|array Function name, or backtrace info if $retCallingLineInfo is true
     */
    public static function getCallerInfo($retInfo = true, $offset = 0)
    {
        $offset ++;
        $info = \bdk\Debug\Utilities::getCallerInfo($offset);
        if ($retInfo) {
            return $info;
        }
        return ($info['class'] ? $info['class'].'::' : '').$info['function'];
    }

    /**
     * Returns cli, cron, ajax, or http
     *
     * @return string cli | cron | ajax | http
     */
    public static function getInterface()
    {
        return Debug\Utilities::getInterface();
    }

    /**
     * Determine PHP's MemoryLimit
     *
     * @return string
     */
    public static function getMemoryLimit()
    {
        $iniVal = ini_get('memory_limit');
        $val = $iniVal !== ''
            ? $iniVal
            : ( version_compare(PHP_VERSION, '5.2.0', '>')
                ? '128M'
                : ( version_compare(PHP_VERSION, '5.2.0', '=')
                    ? '16M'
                    : '8M'
                )
            );
        return $val;
    }

    /**
     * A lot of common $_SERVER values aren't always necessarily set..
     * set default values
     *
     * @return void
     */
    public static function normalizeServerVar()
    {
        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            // CLI : use dir of calling file as "DOCUMENT_ROOT"
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $end = end($backtrace);
            $_SERVER['DOCUMENT_ROOT'] = dirname($end['file']);
        }
        if (substr($_SERVER['DOCUMENT_ROOT'], -1) == '/') {
            // DOCUMENT_ROOT ends with a trailing '/' -> toss it
            $_SERVER['DOCUMENT_ROOT'] = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        }
        $_SERVER = array_merge(array(
            'CONTENT_TYPE' => null,             // ie application/json post REQUEST
            'HTTP_ACCEPT_CHARSET' => null,
            'HTTP_HOST'     => isset($_SERVER['SERVER_ADDR'])
                            ? $_SERVER['SERVER_ADDR']
                            : '127.0.0.1',
            'HTTP_REFERER'      => null,
            'HTTP_USER_AGENT'   => '',
            'HTTP_X_REQUESTED_WITH' => null,    // is XMLHttpRequest with AJAX
            'QUERY_STRING' => '',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_METHOD' => PHP_SAPI == 'cli' // isset($_SERVER['SESSIONNAME']) && $_SERVER['SESSIONNAME'] == 'Console'
                            ? 'CONSOLE'         // in addition to GET, HEAD, POST, & PUT
                            : '',
            'REQUEST_URI'   => $_SERVER['SCRIPT_NAME'] . (!empty($_SERVER['QUERY_STRING']) ?
                '?'. $_SERVER['QUERY_STRING'] : ''),
            'SERVER_ADDR'   => '127.0.0.1',
            'SERVER_NAME'   => '',
        ), $_SERVER);
        foreach (array('SERVER_ADDR','REMOTE_ADDR') as $k) {
            if ($_SERVER[$k] == '::1') {
                $_SERVER[$k] = '127.0.0.1';
            }
        }
        if (empty($_SERVER['QUERY_STRING']) && strpos($_SERVER['REQUEST_URI'], '?') !== false) {
            $_SERVER['QUERY_STRING'] = substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], '?')+1);
            $_GET = Str::parse($_SERVER['QUERY_STRING']);   // allow "." in keys
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != 'unknown') {
            // only use X-Fowarded-For if REMOTE_ADDR is local
            $net = new Net();
            if ($net->isIpLocal($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR_ORIG'] = $_SERVER['REMOTE_ADDR'];
                $parts = preg_split('/,\s*/', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $_SERVER['REMOTE_ADDR'] = $parts[0];
            }
        }
        if (preg_match('|^https?://.*?(/.*)$|', $_SERVER['REQUEST_URI'], $matches)) {
            $_SERVER['REQUEST_URI'] = $matches[1];
        }
        if (!isset($_SERVER['REQUEST_TIME'])) {
            $_SERVER['REQUEST_TIME'] = time();  // avail since PHP 5.1.0
        }
    }
}
