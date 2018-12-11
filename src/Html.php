<?php

namespace bdk;

use bdk\ArrayUtil;
use bdk\Config;
use bdk\Str;

/**
 * HTML methods
 */
class Html extends Config
{

    static protected $cfgStatic = array(
        // 'command','keygen',
        'emptyTags' => array('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'),
        'selfUrl' => array(
            'keepParams'    => true,
            'fullUrl'       => false,
            'chars'         => true,    // whether to htmlspecialchars the url
            'inclPathInfo'  => true,
            'inclIndex.php' => false,   // will not be removed if INFO_PATH is present/not removed
            'getProp'       => array(), // GET params to ALWAYS propagate // 'page','debug'
            'getLock'       => array(), // GET params whose default should come via REQUEST_URI, not $_GET
        ),
    );
    static private $htmlentitiesMore = array(
        140 => '&OElig;',
        156 => '&oelig;',
        138 => '&Scaron;',
        154 => '&scaron;',
        159 => '&Yuml;',
        136 => '&circ;',
        152 => '&tilde;',
        130 => '&sbquo;',
        131 => '&fnof;',
        132 => '&bdquo;',
        133 => '&hellip;',
        134 => '&dagger;',
        135 => '&Dagger;',
        145 => '&lsquo;',
        146 => '&rsquo;',
        147 => '&ldquo;',
        148 => '&rdquo;',
        149 => '&bull;',
        150 => '&ndash;',
        151 => '&mdash;',
        153 => '&trade;',
        137 => '&permil;',
        139 => '&lsaquo;',
        155 => '&rsaquo;',
    );
    static private $xmlentitiesHtml = array();
    static private $xmlentitiesUtf8 = array();

    /**
     * [getCfg description]
     *
     * @return array
     */
    /*
    protected static function getCfg()
    {
        if (isset(self::$htmlentitiesMore[140])) {
            foreach (self::$htmlentitiesMore as $k => $v) {
                unset(self::$htmlentitiesMore[$k]);
                self::$htmlentitiesMore[chr($k)] = $v;
            }
        }
        $defaultCfg = Config::get('Html');
        if ($defaultCfg) {
            self::$cfg = ArrayUtil::mergeDeep(self::$cfg, $defaultCfg);
        }
        return self::$cfg;
    }
    */

    /**
     * Build an attribute string
     *
     * @param array   $attribs the key/values
     *                           class may be passed as an array
     * @param boolean $dblEnc  should attributes be double encoded? (default = false)
     *
     * @return string
     */
    public static function buildAttribString($attribs, $dblEnc = false)
    {
        if (!$dblEnc && \is_array($attribs)) {
            foreach ($attribs as $k => $v) {
                if (\is_string($v)) {
                    $attribs[$k] = \htmlspecialchars_decode($v);
                }
            }
        }
        return \bdk\Debug\Utilities::buildAttribString($attribs);
    }

    /**
     * Similar to http://www.php.net/function.http-build-query
     *
     * @param array  $params    parameters
     * @param string $separator = '&'
     * @param string $key       {@internal}
     *
     * @return string
     */
    public static function buildQueryString($params, $separator = '&', $key = null)
    {
        $query = '';
        foreach ($params as $k => $v) {
            $k = urlencode($k);
            if (!empty($key)) {
                $k = $key.'['.$k.']';
            }
            if (is_null($v)) {
                continue;
            } elseif (is_array($v)) {
                $query .= call_user_func(array(__CLASS__, __FUNCTION__), $v, $separator, $k).$separator;
            } else {
                if ($v === false) {
                    $v = 0;
                } elseif ($v === true) {
                    $v = '';
                }
                $v = str_replace(array('%2F','%2C','%3A','%7C'), array('/',',',':','|'), urlencode($v));
                $query .= $k.'='.$v.$separator;
            }
        }
        $query = rtrim(str_replace('='.$separator, $separator, $query), $separator);
        return $query;
    }

    /**
     * Build an html tag
     *
     * @param string $name      tag name (ie "div" or "input")
     * @param array  $attribs   key/value attributes
     * @param string $innerhtml inner HTML if applicable
     * @param string $dblEnc    whether attribute values sohuld be could encoded
     *
     * @return string
     */
    public static function buildTag($name, $attribs = array(), $innerhtml = '', $dblEnc = false)
    {
        $attribStr = self::buildAttribString($attribs, $dblEnc);
        return \in_array($name, self::$cfgStatic['emptyTags'])
            ? '<'.$name.$attribStr.' />'
            : '<'.$name.$attribStr.'>'.$innerhtml.'</'.$name.'>';
    }

    /**
     * opposite of self::parseUrl / parse_url()
     * parts:
     *  scheme - e.g. http.
     *      automatically added if host part is passed
     *      use "omit" to exclude and create //www.google.com/ type url)
     *  host
     *  port
     *  user
     *  pass
     *  path
     *  query       - after the question mark
     *  fragment    - #anchor   (may also pass fragment via params[_anchor_])
     * also accepts
     *  url         - will be used as defaults;
     *                  if url has hot part but no scheme part, then scheme will default to "omit"
     *                  if 'path' is passed, url's query & fragment will be discarded
     *  params      - these key/values will be merged with the query
     *
     * @param array $parts key/value array of url parts
     *
     * @return string
     */
    public static function buildUrl($parts)
    {
        $return = '';
        $parts = self::buildUrlMergeUrlParts($parts);
        $return = self::buildUrlSchemeHostPort($parts);
        if ($parts['path'] && \substr($parts['path'], 0, 1) != '/') {
            // need a slash
            $parts['path'] = '/'.$parts['path'];
        }
        if ($parts['query']) {
            $parts['query'] = \ltrim($parts['query'], '?');
            $parts['query'] = \htmlspecialchars_decode($parts['query']);
        }
        if (!empty($parts['params'])) {
            // merge passed params onto passed query
            $params = Str::parse($parts['query']);
            $params = ArrayUtil::mergeDeep($params, $parts['params']);
            if (isset($params['_anchor_'])) {
                $parts['fragment'] = $params['_anchor_'];
                unset($params['_anchor_']);
            }
            $parts['query'] = self::buildQueryString($params);
        }
        $return .= $parts['path'];
        if ($parts['query']) {
            $return .= '?'.$parts['query'];
        }
        if ($parts['fragment']) {
            $return .= '#'.$parts['fragment'];
        }
        return $return;
    }

    /**
     * Add or remove a class name
     * 1st param can be
     *      string (tag, attrib string, or class attrib value) - empty string will default to string_class
     *      array  (attribs or classes) - empty array will default to array_class
     *
     * @param array|string $aos        what we're adding/removing classNames to
     * @param array|string $classnames to add or remove
     * @param string       $aor        ['add'] or 'remove'
     * @param string       $aosIs      optional... specify what type of data $aos is
     *                              tag (string),
     *                              attribs (string or array),
     *                              class (string or array)
     *
     * @return mixed
     */
    /*
    public static function classAttrib($aos, $classnames, $aor = 'add', $aosIs = null)
    {
        \bdk\Debug::getInstance()->setErrorCaller();
        $new = $aor == 'add' ? 'classAdd' : 'classRemove';
        trigger_error(__FUNCTION__.'() is deprecated.  Use '.$new.' instead', E_USER_DEPRECATED);
        if ($aor == 'add') {
            return self::classAdd($aos, $classnames, $aosIs);
        } else {
            return self::classRemove($aos, $classnames, $aosIs);
        }
    }
    */

    /**
     * Add classname(s)
     *
     * @param array|string $aos        'tag', attribute array/string, or class array/string
     * @param array|string $classnames classname(s) we're adding
     * @param string       $aosIs      specify 'attribs' or 'class' if it's vague what we're modifying
     *                                   ie, we'll assume an empty array is an empty array of classnames
     *                                   likewise, we'll assume an empty string is an empty string of classnames
     *
     * @return array or string
     */
    public static function classAdd($aos, $classnames, $aosIs = null)
    {
        $parts = self::classNormalize($aos, $aosIs);
        if (!\is_array($classnames)) {
            $classnames = \explode(' ', $classnames);
        }
        $parts['attribs']['class'] = \array_merge($parts['attribs']['class'], $classnames);
        return self::classRebuild($parts);
    }

    /**
     * Remove classname(s)
     *
     * @param array|string $aos        'tag', attribute array/string, or class array/string
     * @param array|string $classnames classname(s) we're removing
     * @param string       $aosIs      specify 'attribs' or 'class' if it's vague what we're modifying
     *                                   ie, we'll assume an empty array is an empty array of classnames
     *                                   likewise, we'll assume an empty string is an empty string of classnames
     *
     * @return array or string
     */
    public static function classRemove($aos, $classnames, $aosIs = null)
    {
        $parts = self::classNormalize($aos, $aosIs);
        if (!\is_array($classnames)) {
            $classnames = \explode(' ', $classnames);
        }
        $parts['attribs']['class'] = \array_diff($parts['attribs']['class'], $classnames);
        return self::classRebuild($parts);
    }

    /**
     * Get absolute URL
     *
     * @param array|string $urlFrom absolute start url
     * @param string       $urlTo   relative url
     *
     * @return string
     */
    public static function getAbsUrl($urlFrom, $urlTo)
    {
        if (strpos($urlTo, '://') !== false) {
            // already absolute;
            return $urlTo;
        }
        $urlAbs = $urlTo;
        if (is_array($urlFrom)) {
            $urlParts = $urlFrom;
        } else {
            $urlParts = Html::parseUrl($urlFrom);
        }
        $urlParts['query']      = null; // do not want absolute url's query
        $urlParts['params']     = array();
        $urlParts['fragment']   = null; // do not want absolute url's fragment
        $path = '';
        if ($urlTo{0} != '/') {
            preg_match('/^(\/.*\/)/', $urlParts['path'], $matches);
            $path = isset($matches[1])
                ? $matches[1]
                : '/';
        }
        $path .= $urlTo;
        $pathSegs = explode('/', $path);
        $pathStack = array();
        foreach ($pathSegs as $k => $seg) {
            if ($seg === '' && $k != 0 && $k < count($pathSegs)-1) {
                continue;
            } elseif ($seg === '.') {
                continue;
            } elseif ($seg === '..') {
                if (array_pop($pathStack) === '') {
                    $pathStack[] = '';
                }
            } else {
                $pathStack[] = $seg;
            }
        }
        $urlParts['path'] = implode('/', $pathStack);
        $urlAbs = Html::buildUrl($urlParts);
        return $urlAbs;
    }

    /**
     * getCfg description
     *
     * @param mixed $path config path
     *
     * @return mixed
     */
    public static function getCfg($path = array())
    {
        if (isset(self::$htmlentitiesMore[140])) {
            foreach (self::$htmlentitiesMore as $k => $v) {
                unset(self::$htmlentitiesMore[$k]);
                self::$htmlentitiesMore[chr($k)] = $v;
            }
        }
        return parent::getCfg($path);
    }

    /**
     * returns the current "requested url"
     *  this "requested url" =
     *      path = path from REQUEST_URI
     *      default query parameters = current $_GET values
     *          exceptions:
     *              param is unchanged from QUERY_STRING and isn't in REQUEST_URI  (ie, mod_rewrite created param)
     *              param is in opts['getLock']
     *                  if $_GET[param] is non-empty -> REQUEST_URI (or non-value) will be used.
     * uses buildUrl(), so may pass an anchor/fragment (#anchor) via params['_anchor_']
     *
     * @param array $paramsNew any paramaters to add/remove from the url
     * @param array $opts      options
     *
     * @return string
     */
    public static function getSelfUrl($paramsNew = array(), $opts = array())
    {
        $return = '';
        if (is_null($paramsNew)) {
            $paramsNew = array();
        }
        $opts = array_merge(self::$cfgStatic['selfUrl'], $opts);
        /*
        REQUEST_URI is what the user sees.
        QUERY_STRING is after any mod_rewrite
        */
        $urlParts = array();
        $urlPartsReq = self::parseUrl($_SERVER['REQUEST_URI']);
        if ($opts['fullUrl']) {
            $urlParts['scheme'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on'
                ? 'https'
                : 'http';
            $urlParts['host'] = $_SERVER['HTTP_HOST'];
        }
        $urlParts['path'] = $urlPartsReq['path'];
        $urlParts['params'] = self::getSelfUrlParams($urlPartsReq['params'], $paramsNew, $opts);
        if (!$opts['inclPathInfo'] && !empty($_SERVER['PATH_INFO'])) {
            $path = urldecode($urlParts['path']);
            $pos = strrpos($path, $_SERVER['PATH_INFO']);
            if ($pos !== false) {
                $urlParts['path'] = substr($path, 0, $pos);
            }
        }
        if (!$opts['inclIndex.php']) {
            $urlParts['path'] = preg_replace('/index\.php$/', '', $urlParts['path']);
        }
        $return = self::buildUrl($urlParts);
        if ($opts['chars']) {
            $return = htmlspecialchars($return);
        }
        return $return;
    }

    /**
     * Convert all applicable characters to HTML entities..
     * does not double encode
     *
     * @param string  $str        string
     * @param boolean $encodeTags [true]
     * @param boolean $isXml      [false]
     * @param string  $charset    [null]
     *
     * @return string
     */
    public static function htmlentities($str = '', $encodeTags = true, $isXml = false, $charset = null)
    {
        $debug = \bdk\Debug::getInstance();
        $dbWas = $debug->setCfg('collect', false);
        $debug->groupCollapsed(__FUNCTION__);
        $return = '';
        $charsetsTry = array('UTF-8','ISO-8859-1');
        $debug->log('charset', $charset);
        if (is_null($charset) && extension_loaded('mbstring')) {
            $charset = mb_detect_encoding($str, mb_detect_order(), true);
            $debug->log('charset detected', $charset);
        }
        if ($charset) {
            $charset = self::getCharset($charset);
            if ($charset == 'UTF-8' && !Str::isUtf8($str)) {
                $charset = null;
            }
        }
        if ($charset) {
            #$debug->log('will try charset '.$charset.' first');
            array_unshift($charsetsTry, $charset);
            $charsetsTry = array_unique($charsetsTry);
        }
        $debug->log('charsetsTry', implode(', ', $charsetsTry));
        $return = self::htmlentitiesApply($str, $encodeTags, $isXml, $charsetsTry);
        #$debug->log('return', $return);
        $debug->groupEnd();
        $debug->setCfg('collect', $dbWas);
        return $return;
    }

    /**
     * Is the passed string a URL?
     *
     * alias of Str::isUrl()
     *
     * @param string $url  string/URL to test
     * @param array  $opts options
     *
     * @return boolean
     */
    public static function isUrl($url, $opts = array())
    {
        return Str::isUrl($url, $opts);
    }

    /**
     * Parse string -o- attributes into a key=>value array
     *
     * @param string  $str        string to parse
     * @param boolean $decode     (true)whether to decode special chars
     * @param boolean $decodeData (true) whether to decode data attribs
     *
     * @return array
     */
    public static function parseAttribString($str, $decode = true, $decodeData = true)
    {
        return \bdk\Debug\Utilities::parseAttribString($str, $decode, $decodeData);
    }

    /**
     * Parse HTML/XML tag
     *
     * returns array(
     *    'tagname' => string
     *    'attribs' => array
     *    'innerhtml' => string | null
     * )
     *
     * @param string $tag html tag to parse
     *
     * @return array
     */
    public static function parseTag($tag)
    {
        return \bdk\Debug\Utilities::parseTag($tag);
    }

    /**
     * Parse a url
     * a wrapper for parse_url, always returns all parts
     * the following additional values are also returned
     *    schemeWasProvided (boolean): if provitedurl doesn't have a scheme, this will be false
     *    domain (string): the domain part of  host
     *    params (array): parsed query string
     *
     * @param string $url url to parse
     *
     * @return array
     */
    public static function parseUrl($url)
    {
        $urlPartsDefault = array(
            'schemeWasProvided' => false,
            // 'hostWasProvided' => false,
            'scheme' => null,
            // 'host' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1',
            'host' => null,
            'domain' => null,       // if host is images.google.com, domain will be google.com
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => '/',
            'query' => '',          // after the questiono mark but not including fragment
            'params' => array(),
            'fragment' => null,     // after the hashmark #
        );
        if (strpos($url, '//') === 0 && version_compare(PHP_VERSION, '5.4.7', '<')) {
            // PHP prior to 5.4.7 incorrectly parses '//www.example.com/path'
            $urlParts = parse_url('temp:'.$url);
            $urlParts['scheme'] = null;
        } else {
            $urlParts = parse_url($url);
        }
        if (!empty($urlParts['scheme'])) {
            $urlParts['schemeWasProvided'] = true;
        }
        /*
        if (!empty($urlParts['host'])) {
            $urlParts['hostWasProvided'] = true;
        }
        */
        $urlParts = array_merge($urlPartsDefault, $urlParts);
        if (empty($urlParts['port'])) {
            $urlParts['port'] = \in_array($urlParts['scheme'], array('http', null))
                ? 80
                : 443;
        }
        if (!empty($urlParts['query'])) {
            $urlParts['params'] = Str::parse($urlParts['query']);
        }
        $hostParts = explode('.', $urlParts['host']);
        if (!is_numeric(end($hostParts))) {
            $domain = implode('.', array_slice($hostParts, -2));
            if (strpos($domain, 'co.') === 0) {
                $domain = implode('.', array_slice($hostParts, -3));
            }
            $urlParts['domain'] = $domain;
        }
        return $urlParts;
    }

    /**
     * [buildEntities description]
     *
     * @return void
     */
    protected static function buildEntities()
    {
        $validXmlEntities = array('&'=>'&amp;', '"'=>'&quot;', '\''=>'&apos;', '<'=>'&lt;', '>'=>'&gt;');
        $transTable = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
        foreach (array_keys($validXmlEntities) as $char) {
            unset($transTable[$char]);
        }
        $more = array(
            '&OElig;'   => '&#338;',
            '&oelig;'   => '&#339;',
            '&Scaron;'  => '&#352;',
            '&scaron;'  => '&#353;',
            '&Yuml;'    => '&#376;',
            '&fnof'     => '&#402;',
            '&circ;'    => '&#710;',
            '&tilde;'   => '&#732;',
            '&ensp;'    => '&#8194;',   // ?
            '&emsp;'    => '&#8195;',   // ?
            '&thinsp;'  => '&#8201;',   // ?
            '&zwnj;'    => '&#8204;',   // ?
            '&zwj;'     => '&#8205;',   // ?
            '&lrm;'     => '&#8206;',   // ?
            '&rlm;'     => '&#8207;',   // ?
            '&ndash;'   => '&#8211;',
            '&mdash;'   => '&#8212;',
            '&lsquo;'   => '&#8216;',
            '&rsquo;'   => '&#8217;',
            '&sbquo;'   => '&#8218;',
            '&ldquo;'   => '&#8220;',
            '&rdquo;'   => '&#8221;',
            '&bdquo;'   => '&#8222;',
            '&dagger;'  => '&#8224;',
            '&Dagger;'  => '&#8225;',
            '&bull;'    => '&#8226;',
            '&hellip;'  => '&#8230;',
            '&permil;'  => '&#8240;',
            '&lsaquo;'  => '&#8249;',
            '&rsaquo;'  => '&#8250;',
            '&euro;'    => '&#8364;',   // ?
            '&trade;'   => '&#8482;',
        );
        foreach ($transTable as $chr => $entity) {
            self::$xmlentitiesHtml[] = $entity;
            self::$xmlentitiesUtf8[] = '&#'.ord($chr).';';
        }
        array_splice(self::$xmlentitiesHtml, count($transTable), 0, array_keys($more));
        array_splice(self::$xmlentitiesUtf8, count($transTable), 0, array_values($more));
        #debug->log('xmlentitiesHtml', self::$xmlentitiesHtml);
        #debug->log('xmlentitiesUtf8', self::$xmlentitiesUtf8);
    }

    /**
     * [buildUrlGetParts description]
     *
     * @param array $parts passed in url parts
     *
     * @return array
     */
    protected static function buildUrlMergeUrlParts($parts)
    {
        $partsDefault = array(
            'scheme'    => null,
            'host'      => null,
            'port'      => null,
            'path'      => '/',
            'query'     => '',
            'fragment'  => null,
        );
        if (!empty($parts['url'])) {
            $urlParts = self::parseUrl(htmlspecialchars_decode($parts['url']));
            if (!$urlParts['scheme']) {
                $urlParts['scheme'] = 'omit';
            }
            if (!empty($parts['path'])) {
                // discard url's query and fragment
                $urlParts['query']      = null;
                $urlParts['params']     = array();
                $urlParts['fragment']   = null;
            }
            if ($urlParts['query'] && !empty($parts['query'])) {
                // merge the queries
                $paramsParts = Str::parse($parts['query']);
                $params = ArrayUtil::mergeDeep($urlParts['params'], $paramsParts);
                $parts['query'] = self::buildQueryString($params);
            }
            $partsDefault = array_merge($partsDefault, $urlParts);
        }
        $parts = array_merge($partsDefault, $parts);
        return $parts;
    }

    /**
     * get scheme://user:pass@host:port
     *
     * @param array $parts url parts
     *
     * @return string
     */
    static protected function buildUrlSchemeHostPort(&$parts)
    {
        $return = '';
        if ($parts['scheme'] == 'omit') {
            $parts['scheme'] = null;
        } elseif (!$parts['scheme'] && !$parts['host']) {
            return '';
        } elseif (!$parts['scheme']) {
            // we passed a host, but no scheme
            if (\in_array($parts['host'], array(
                    '127.0.0.1',
                    'localhost',
                    isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null,
            ))) {
                $local = true;
            } else {
                $regex = '#(.+\.)?(\w+.[a-z])$#';
                $hostDomain = \preg_replace($regex, '$1', $parts['host']);
                $localDomain = \preg_replace($regex, '$1', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null);
                $local = $hostDomain == $localDomain;
            }
            if ($local) {
                $parts['scheme'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on'
                    ? 'https'
                    : 'http';
            } else {
                $parts['scheme'] = 'http';
            }
        } elseif (!\in_array($parts['scheme'], array('http','https'))) {
            $parts = \array_diff_key($parts, \array_flip(array('query','fragment','params')));
        }
        if ($parts['scheme']) {
            $return .= $parts['scheme'].':';
        }
        if ($parts['host']) {
            $return .= '//';
            $parts = \array_merge(array('user'=>null,'pass'=>null), $parts);
            if (!empty($parts['user'])) {
                $return .= $parts['user'].\rtrim(':'.$parts['pass'], ':').'@';
            }
            $return .= $parts['host'];
            if ($parts['port']) {
                $defaultPorts = array(
                    'http' => 80,
                    'https' => 443,
                );
                if ($parts['scheme']) {
                    if (isset($defaultPorts[ $parts['scheme'] ]) && $parts['port'] == $defaultPorts[ $parts['scheme'] ]) {
                        $parts['port'] = null;
                    }
                } elseif (in_array($parts['port'], $defaultPorts)) {
                    $parts['port'] = null;
                }
                $return .= \rtrim(':'.$parts['port'], ':');
            }
        }
        return $return;
    }

    /**
     * determine what was passed (tag, attribs, or class)
     *
     * empty array will return 'class'
     * empty string will return 'class'
     *
     * @param mixed $aos string (tag,attribs,class) or array (attribs,class)
     *
     * @return string
     */
    private static function classDeductParam($aos)
    {
        if (is_string($aos) || is_null($aos)) {
            $aos = trim($aos);
            if (substr($aos, 0, 1) == '<') {
                $aosIs = 'tag';
            } elseif (strpos($aos, '=')) {
                $aosIs = 'attribs';
            } else {
                $aosIs = 'class';
            }
        } else {
            if (ArrayUtil::isHash($aos)) {
                $aosIs = 'attribs';
            } else {
                $aosIs = 'class';
            }
        }
        return $aosIs;
    }

    /**
     * [classNormalize description]
     *
     * @param array|string $aos   'tag', attribute array/string, or class array/string
     * @param string       $aosIs 'tag', 'attribs', or 'class'
     *
     * @return array
     */
    private static function classNormalize($aos, $aosIs)
    {
        $aosIs = $aosIs ?: self::classDeductParam($aos);
        if ($aosIs == 'tag') {
            $tagParts = self::parseTag($aos);
        } elseif ($aosIs == 'attribs') {
            $attribsIsArray = \is_array($aos);
            $tagParts = array(
                'attribs' => $attribsIsArray
                    ? $aos
                    : self::parseAttribString($aos),
                'attribsIsArray' => $attribsIsArray,
            );
        } elseif ($aosIs == 'class') {
            $tagParts = array(
                'attribs' => array('class' => $aos),
            );
        }
        $tagParts['aosIs'] = $aosIs;
        if (!isset($tagParts['attribs']['class'])) {
            $tagParts['attribs']['class'] = '';
        }
        if (\is_array($tagParts['attribs']['class'])) {
            $tagParts['classIsArray'] = true;
        } else {
            $tagParts['attribs']['class'] = \explode(' ', $tagParts['attribs']['class']);
            $tagParts['classIsArray'] = false;
        }
        return $tagParts;
    }

    /**
     * Return tag, attribs (string or array), or class (string or array)
     *
     * @param array $parts [description]
     *
     * @return array|string
     */
    private static function classRebuild($parts)
    {
        $parts['attribs']['class'] = \array_unique($parts['attribs']['class']);
        $parts['attribs']['class'] = \array_filter($parts['attribs']['class'], 'strlen');
        \sort($parts['attribs']['class']);
        if (!$parts['classIsArray']) {
            $parts['attribs']['class'] = \implode(' ', $parts['attribs']['class']);
        }
        if ($parts['aosIs'] == 'tag') {
            return self::buildTag($parts['tagname'], $parts['attribs'], $parts['innerhtml']);
        } elseif ($parts['aosIs'] == 'attribs') {
            return $parts['attribsIsArray']
                ? $parts['attribs']
                : self::buildAttribString($parts['attribs']);
        } elseif ($parts['aosIs'] == 'class') {
            return $parts['attribs']['class'];
        }
    }

    /**
     * [getCharset description]
     *
     * @param string $charset character set
     *
     * @return string|null
     */
    protected static function getCharset($charset)
    {
        // $charset_default = 'ISO-8859-1';
        // $charset_default = ini_get('default_charset');
        $supportedCharsets = array(
            'UTF-8'         => '',
            'ISO-8859-1'    => 'ISO8859-1, ASCII',
            'ISO-8859-15'   => 'ISO8859-15',
            'cp866'         => 'ibm866, 866',
            'cp1251'        => 'Windows-1251, win-1251, 1251',
            'cp1252'        => 'Windows-1252, 1252',
            'KOI8-R'        => 'koi8-ru, koi8r',
            'BIG5'          => '950',
            'GB2312'        => '936',
            'BIG5-HKSCS'    => '',
            'Shift_JIS'     => 'SJIS, 932',
            'EUC-JP'        => 'EUCJP',
        );
        $found = in_array($charset, array_keys($supportedCharsets));
        if ($found) {
            return $charset;
        }
        foreach ($supportedCharsets as $k => $v) {
            if (strpos($v, $charset) !== false) {
                $found = true;
                $charset = $k;
                break;
            }
        }
        return $charset;
    }

    /**
     * get parameters for self url
     *
     * @param array $requestParams REQUEST_URI params
     * @param array $paramsNew     new parameters
     * @param array $opts          options
     *
     * @return array
     */
    private static function getSelfUrlParams($requestParams, $paramsNew, $opts)
    {
        $params = $requestParams;
        $paramsQuery = Str::parse($_SERVER['QUERY_STRING']);    // params from QUERY_STRING
        // compare $_GET with QUERY_STRING & REQUEST_URI to see what's been added/removed/changed
        //  if a param has changed between QUERY_STRING & $_GET, it will be added as a default
        //  if in QUERY_STRING, but not in REQUEST_URI, it will NOT be added as a default
        $cor = array_diff_assoc($paramsQuery, $_GET);   // Changed Or Removed params
        $coa = array_diff_assoc($_GET, $paramsQuery);   // Changed Or Added params
        foreach ($cor as $k => $v) {
            if (!isset($coa[$k])) {
                unset($params[$k]); // it's a remove
            } elseif (isset($coa[$k]) && $coa[$k] != $v) {
                continue;   // $_GET[param] has changed in code
            } elseif (!isset($requestParams[$k])) {
                // $k is in QUERY_STRING, but not REQUEST_URI (mod_rewrite created)
                unset($coa[$k]);
            }
        }
        foreach ($coa as $k => $v) {
            $params[$k] = $v;       // it's a change or add
        }
        if (!empty($opts['getLock'])) {
            foreach ($opts['getLock'] as $k) {
                if (isset($params[$k])) {
                    $params[$k] = isset($requestParams[$k])
                        ? $requestParams[$k]    // use REQUEST_URI val
                        : null;                         // not in REQUEST_URI
                }
            }
        }
        if (!$opts['keepParams']) {
            // keep default params only if in getProp
            foreach ($params as $k => $v) {
                if (!in_array($k, $opts['getProp'])) {
                    unset($params[$k]);
                }
            }
        }
        foreach ($paramsNew as $k => $v) {
            $params[$k] = $v;
        }
        return $params;
    }

    /**
     * [htmlentitiesApply description]
     *
     * @param string  $str        string to encode
     * @param boolean $encodeTags apply to html tags ?
     * @param boolean $isXml      is the string xml?
     * @param array   $charsets   character sets to try
     *
     * @return string
     */
    protected static function htmlentitiesApply($str, $encodeTags, $isXml, $charsets)
    {
        self::getCfg(); // sets htmlentitiesmore
        $return = '';
        if (!$encodeTags) {
            // $debug->log('don\'t encode tags');
            $return = self::htmlentitiesSansTags($str, $charsets);
        }
        // now entityfy final (or only) segment
        if (!in_array(trim($str), array('', '0'), true)) {
            foreach ($charsets as $charset) {
                // change: was call_user_func_array
                $strEntityfied = @htmlentities($str, ENT_COMPAT, $charset);
                if (!empty($strEntityfied)) {
                    #$debug->log('success with '.$charset);
                    break;
                }
            }
            if (empty($strEntityfied)) {
                // $debug->warn('unable to convert');
                $strEntityfied = $str;
            }
            $str = preg_replace('/&amp;(#\d+;|[a-zA-Z]+;)/', '&\\1', $strEntityfied);
            $str = str_replace(
                array_keys(self::$htmlentitiesMore),
                array_values(self::$htmlentitiesMore),
                $str
            );
            $return .= $str;
        }
        if ($isXml) {
            if (empty(self::$xmlentitiesHtml)) {
                self::buildEntities();
            }
            $return = str_replace(self::$xmlentitiesHtml, self::$xmlentitiesUtf8, $return);
        }
        return $return;
    }

    /**
     * [htmlentitiesSansTags description]
     *
     * @param string $str      string to convert
     * @param array  $charsets character sets to attempt
     *
     * @return string
     */
    protected static function htmlentitiesSansTags(&$str, $charsets)
    {
        $return = '';
        $os_oc = 0;
        while (preg_match('#<!--|-->|</?[^>]+>#is', $str, $m_oc, PREG_OFFSET_CAPTURE, $os_oc)) {
            $tag    = $m_oc[0][0];
            $os_tag = $m_oc[0][1];
            $strSegment = substr($str, $os_oc, $os_tag-$os_oc);
            if (!in_array(trim($strSegment), array('', '0'), true)) {
                foreach ($charsets as $charset) {
                    // change: was call_user_func_array
                    $strEntityfied = @htmlentities($strSegment, ENT_COMPAT, $charset);
                    if (!empty($strEntityfied)) {
                        #$debug->log('success with '.$charset);
                        break;
                    }
                }
                if (empty($strEntityfied)) {
                    $strEntityfied = $strSegment;
                }
                $strSegment = preg_replace('/&amp;(#\d+;|[a-zA-Z]+;)/', '&\\1', $strEntityfied);
                $strSegment = str_replace(
                    array_keys(self::$htmlentitiesMore),
                    array_values(self::$htmlentitiesMore),
                    $strSegment
                );
            }
            $return .= $strSegment.$tag;
            $os_oc = $os_tag + strlen($tag);
        }
        $str = substr($str, $os_oc);
        return $return;
    }

}
