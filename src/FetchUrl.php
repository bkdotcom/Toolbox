<?php

namespace bdk;

use bdk\ArrayUtil;
use bdk\Config;
use bdk\Gzip;
use bdk\Html;
use bdk\Net;

/**
 * FetchUrl
 */
class FetchUrl extends Config
{

    public $curlInfo = array();
    public $fetchError;
    public $fetchResponse;
    // public $scrapeInfo = array();
    protected static $cfgStatic = array(
        'curl_opts' => array(),
    );
    private $cfg = array();
    private $debug = null;
    private $optionsPassed = array();
    private $redirectHistory = array();

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        $this->debug = \bdk\Debug::getInstance();
        $this->cfg = \array_merge(self::$cfgStatic, $cfg);
    }

    /**
     * Fetch URL
     *
     * @param string $url     url to fetch
     * @param array  $options options
     *
     * @return mixed array on success, false on error
     */
    public function fetch($url, $options = array())
    {
        $this->debug->groupCollapsed(__METHOD__);
        $optionsDefault = $this->getOptionsDefault();
        $options = $this->getOptions($optionsDefault, $options);
        $urlParts = Html::parseUrl($url);
        $useCurl = $urlParts['scheme'] == 'https' || \array_diff_key($options, $optionsDefault);
        $return = $useCurl
            ? $this->fetchCurl($url, $options)
            : $this->fetchFsock($url, $options);
        #$this->debug->log('return',$return);
        $this->debug->groupEnd();
        return $return;
    }

    /**
     * Essentially a shorthand for Cache & FetchUrl
     *
     * @param array $cfg configuration
     *
     * @return array
     */
    /*
    public function scrape($cfg)
    {
        $this->debug->groupCollapsed(__METHOD__);
        // $this->debug->log('cfg', $cfg);
        $return = false;
        if (Php::getCallerInfo(false) != __NAMESPACE__.'\Cache::load') {
            $cfg_defaults = array(
                // 'url'
                'regex'         => null,
                'on_get'        => null,
                'cache_dir'     => null,    // default = same dir as calling file
                'cache_filename'=> null,    // optional specify path to
                'cache_filepath'=> null,    // optional
                'cache_prefix'  => 'cache_',
                'cache_name'    => null,    // optional = default = md5 of url/values/regex/on_get
                'cache_ext'     => 'txt',   // only used when cache filename is generated automatically
                'trash_collect' => 0,       // Use with CAUTION... see Cache class
                'cache_type'    => 'file',  // (file | csv)
                'cache_min'     => null,    // pass min or sec
                'cache_sec'     => null,    // sec is given precidence
            );
            $cfg = array_merge($cfg_defaults, $cfg);
            if (isset($cfg['cache_min']) || isset($cfg['cache_sec'])) {
                if (!isset($cfg['cache_sec'])) {
                    $cfg['cache_sec'] = $cfg['cache_min'] * 60;
                }
                unset($cfg['cache_min']);
                if (!empty($cfg['cache_filepath'])) {
                    $this->debug->log('cache_filepath explicitly specified');
                } elseif (!empty($cfg['cache_filename'])) {
                    if (!isset($cfg['cache_dir'])) {
                        $backtrace = debug_backtrace();
                        $cfg['cache_dir'] = dirname($backtrace['0']['file']);
                    }
                    $cfg['cache_filepath'] = $cfg['cache_dir'].'/'.$cfg['cache_filename'];
                } else {
                    if (!isset($cfg['cache_dir'])) {
                        $backtrace = debug_backtrace();
                        $cfg['cache_dir'] = dirname($backtrace['0']['file']);
                    }
                    if (!empty($cfg['cache_name'])) {
                        // cache_name passed... add prefix and ext
                        $fn = $cfg['cache_name'];
                        if (!empty($cfg['cache_prefix']) && strpos($fn, $cfg['cache_prefix']) !== 0) {
                            $fn = $cfg['cache_prefix'].$fn;
                        }
                        if (!empty($cfg['cache_ext']) && !preg_match('/\.[a-z]{3,4}$/', $fn)) {
                            $fn = $fn.'.'.$cfg['cache_ext'];
                        }
                        $cfg['cache_filename'] = $fn;
                    } else {
                        // md5 url, post-values and whatnot for cacge filename
                        $post = '';
                        if (!empty($cfg['CURLOPT_POSTFIELDS'])) {
                            $post = $cfg['CURLOPT_POSTFIELDS'];
                        } elseif (!empty($cfg['post'])) {
                            $post = $cfg['post'];
                        }
                        $md5 = md5(serialize(array($cfg['url'],$post,$cfg['regex'],$cfg['on_get'])));
                        $fn = $cfg['cache_prefix'].$md5;
                        if (!empty($cfg['cache_ext'])) {
                            $fn = $fn.'.'.$cfg['cache_ext'];
                        }
                        $cfg['cache_filename'] = $fn;
                    }
                    foreach (array('cache_prefix','cache_name','cache_ext') as $k) {
                        unset($cfg[$k]);
                    }
                    $cfg['cache_filepath'] = $cfg['cache_dir'].'/'.$cfg['cache_filename'];
                }
                $cfg['cache_filepath'] = preg_replace('#[/\\\]#', DIRECTORY_SEPARATOR, $cfg['cache_filepath']);
            }
            $cache = new Cache(array(
                'cache_type'    => $cfg['cache_type'],
                'cache_filepath'=> $cfg['cache_filepath'],
                'cache_sec'     => $cfg['cache_sec'],
                'trash_collect' => $cfg['trash_collect'],
                'load_func'     => array($this,__FUNCTION__),
                'load_func_params' => array($cfg),
            ));
            $return = $cache->get();
            $this->scrapeInfo = $cache->info;
        } else {
            if ($return = $this->fetch($cfg['url'], $cfg)) {
                #$this->debug->log('return', $return);
                if (!empty($cfg['regex'])) {
                    $this->debug->log('applying regex');
                    preg_match($cfg['regex'], $return['body'], $return);
                    if (count($return) == 1) {
                        $return = $return[0];
                    } elseif (count($return) == 2) {
                        $return = $return[1];
                    }
                } elseif ($cfg['on_get']) {
                    $this->debug->log('on_get', $cfg['on_get']);
                    if (is_callable($cfg['on_get'])) {
                        $this->debug->log('function exists');
                        $return = call_user_func($cfg['on_get'], $return, $cfg);
                    }
                } else {
                    $this->fetchResponse = $return;
                    $return = $return['body'];
                }
            }
        }
        #$this->debug->log('return', $return);
        $this->debug->groupEnd();
        return $return;
    }
    */

    /**
     * for a string containing both headers and content, return the header portion
     *
     * @param string $str headers + content
     *
     * @return string
     */
    private function extractHeaders($str)
    {
        $this->debug->log(__METHOD__);
        $this->debug->groupUncollapse();
        $headerStart = 0;
        #$this->debug->log(('top', \substr($return, 0, $this->curlInfo['header_size']));
        $headerLenStack = array();
        $i = 0;
        while (true) {
            $headerEnd     = \strpos($str, "\r\n\r\n", $headerStart);
            $headerLength  = $headerEnd - $headerStart;
            $headers       = \substr($str, $headerStart, $headerLength);
            $headerStart   = $headerEnd+4;
            $headerLenStack[] = \strlen($headers)+4;
            #$this->debug->log('headers', \str_replace(array("\r","\n"), array('|','|'), $headers));
            if (\count($headerLenStack) > $this->curlInfo['redirect_count']) {
                #$this->debug->log('header_len_stack','['.\implode(',',$header_len_stack).']');
                $sum = 0;
                for ($j=$i; $j>=0; $j--) {
                    $sum += $headerLenStack[$j];
                    if ($sum == $this->curlInfo['header_size']) {
                        #$this->debug->info('found headers');
                        break 2;
                    }
                }
            }
            if ($headerEnd === false || $i > 20) {
                $this->debug->warn('error parsing headers');
                break;
            }
            $i++;
        }
        // $this->debug->log('headers', $headers);
        return $headers;
    }

    /**
     * Fetch a URL via fsockopen
     *
     * @param string $url     url to fetch
     * @param array  $options options
     *
     * @return array|false
     */
    protected function fetchFsock($url, $options = array())
    {
        $this->debug->groupCollapsed(__METHOD__);
        $return = false;
        $urlParts = Html::parseUrl($url);
        // $this->debug->log('urlParts', $urlParts);
        // $this->debug->log('options', $options);
        if (!empty($options['proxy']) && !\in_array($urlParts['host'], array('127.0.0.1','localhost'))) {
            $this->debug->log('proxy', $options['proxy']);
            $request = 'GET '.$url.' HTTP/1.0'."\r\n";
            $proxyParts = \parse_url($options['proxy']);
            $pointer = \fsockopen($proxyParts['host'], $proxyParts['port'], $errno, $errstr, $options['timeout']);
        } else {
            $uri = $urlParts['path'].( !empty($urlParts['query']) ? '?'.$urlParts['query'] : '' );
            $request = 'GET '.$uri.' HTTP/1.0'."\r\n";
            $pointer = \fsockopen($urlParts['host'], $urlParts['port'], $errno, $errstr, $options['timeout']);
        }
        if ($pointer) {
            $host = $urlParts['host'].( $urlParts['port'] != '80' ? ':'.$urlParts['port'] : '' );
            $return = '';
            $request .= ''
                .'Host: '.$host."\r\n"
                .'Referer: '.Html::getSelfUrl(array(), array('fullUrl'=>true,'chars'=>false))."\r\n"
                .( !empty($urlParts['user'])
                    ? 'Authorization: Basic '.\base64_encode($urlParts['user'].':'.$urlParts['pass'])."\r\n"
                    : ''
                )
                ."\r\n";
            $this->debug->log('request', $request);
            \fputs($pointer, $request);
            while (!\feof($pointer)) {
                $return .= \fread($pointer, 4096);
            }
            \fclose($pointer);
            $this->debug->log('return', $return);
            $header_start = 0;
            while (true) {
                $header_end     = \strpos($return, "\r\n\r\n", $header_start);
                $header_length  = $header_end-$header_start;
                $headers        = \substr($return, $header_start, $header_length);
                $header_start   = $header_end+4;
                if (!\preg_match('/^HTTP.+Continue/i', $headers)) {
                    break;
                }
            }
            $this->debug->log('headers', $headers);
            $parsedHeaders = Net::parseHeaders($headers);
            $return = array(
                'headers'   => $parsedHeaders,
                'body'      => \substr($return, $header_end+4),
                'cookies'   => $this->getCookies($parsedHeaders),
            );
            $this->fetchResponse = $return;
            if (isset($return['headers']['Location'])) {
                $this->debug->log('headers', $return['headers']);
                $location = Html::getAbsUrl($urlParts, $return['headers']['Location']);
                $return = $this->fetch($location, $options);
            } elseif ($return['headers']['Return-Code'] != '200') {
                $this->debug->log('return[headers]', $return['headers']);
                if (isset($return['headers']['Proxy-Authenticate'])
                    && ( $return['headers']['Proxy-Authenticate'] == 'NTLM' || \in_array('NTLM', $return['headers']['Proxy-Authenticate']))
                ) {
                    $return = $this->fetchCurl($url, $options);
                    $return['headers']['Current-Location'] = $url;
                } else {
                    $this->debug->log('return was', $return);
                    $return = false;
                }
            }
        } else {
            $this->debug->warn('Error ('.$errno.') : ', $errstr);
        }
        $this->debug->groupEnd();
        return $return;
    }

    /**
     * Fetch URL using CURL
     *  returns assoc array(
     *      headers     => array,
     *      page        => string,
     *      cookies     => array,
     *  )
     *
     * @param string $url     url to fetch
     * @param array  $options options
     *
     * @return array
     * @todo   beef up location following to handle ports/username/password, etc..
     */
    protected function fetchCurl($url, $options = array())
    {
        $this->debug->groupCollapsed(__METHOD__, $url);
        $return                 = false;
        $this->curlInfo         = null;
        $this->fetchError       = null;     // CURL error
        $this->fetchResponse    = null;     // contains the return assoc array if return is false due to HTTP return code != 200...
        $this->optionsPassed    = $options;
        $this->redirectHistory[] = $url;
        if (!\function_exists('curl_init')) {
            \trigger_error('curl not installed');
            $this->groupEnd();
            return false;
        }
        $curl = \curl_init();
        if ($curl === false) {
            $this->debug->warn('curl_init() returned false');
            $this->groupEnd();
            return false;
        }
        // options: http://www.php.net/curl_setopt
        $this->options = $this->curlGetOptions($url, $options);
        $this->curlSetOptions($curl, $this->options);
        $this->fetchResponse = \curl_exec($curl);
        $this->curlInfo = \curl_getinfo($curl);

        /*
            Debugging
        */
        if (!empty($this->options['curl_getinfo'])) {
            $this->debug->log('curl_version', \curl_version());
            $this->debug->log('curl_getinfo', $this->curlInfo);
        } elseif (isset($this->curlInfo['request_header'])) {
            $this->debug->info('request', $this->curlInfo['request_header']);
        } else {
            $this->debug->warn('request_header is unavailable');
        }
        if ($this->options['verbose']) {
            $pointer = $this->options['CURLOPT_STDERR'];
            \rewind($pointer);
            $this->debug->info('verbose', \stream_get_contents($pointer));
        }

        if (\curl_error($curl)) {
            $this->fetchError = \curl_errno($curl).' '.\curl_error($curl);
            $this->debug->warn('CURL error : '.$this->fetchError);
            \curl_close($curl);
            $return = false;
        } else {
            \curl_close($curl);   // close early to avoid fatal memory allocation error on large returns
            if ($this->options['CURLOPT_HEADER']) {
                $this->debug->info('redirect_count', $this->curlInfo['redirect_count']);
                $this->debug->info('header_size', $this->curlInfo['header_size']);
                $headers = $this->extractHeaders($this->fetchResponse);
                $headers = Net::parseHeaders($headers);
                $headers['Current-Location'] = $this->options['CURLOPT_URL'];
                // $this->debug->log('headers', $headers);
                $this->fetchResponse = array(
                    'headers'   => $headers,
                    'body'      => \substr($this->fetchResponse, $this->curlInfo['header_size']),
                    'cookies'   => $this->getCookies($headers),
                );
            } else {
                $this->fetchResponse = array(
                    'headers'   => array(),
                    'body'      => $this->fetchResponse,
                    'cookies'   => array(),
                );
            }
            $return = $this->curlGetReturn();
        }
        $this->debug->groupEnd();
        return $return;
    }

    /**
     * Get complete options
     *
     * @param string $url     url to fetch
     * @param array  $options array o options
     *
     * @return array
     */
    protected function curlGetOptions($url, $options = array())
    {
        $this->debug->groupCollapsed(__METHOD__);
        $urlParts = Html::parseUrl($url);
        $optionsDefault = $this->curlOptionsDefault($url);
        $options = $this->curlOptionsNormalize($options);
        // $this->debug->log('optionsDefault', $optionsDefault);
        $options = ArrayUtil::mergeDeep($optionsDefault, $options);
        $options['CURLOPT_URL'] = \str_replace(' ', '%20', $options['CURLOPT_URL']);
        $options['CURLOPT_FOLLOWLOCATION']  = false;    // we will follow manually
        if ($options['verbose']) {
            $options['CURLOPT_VERBOSE'] = true;
            /*
            $options['tempfile'] = tempnam(\sys_get_temp_dir(), 'curl_');
            if (\is_writable($options['tempfile'])) {
                $fh = \fopen($options['tempfile'], 'w+');
                $options['CURLOPT_STDERR'] = $fh;
            }
            */
            $options['CURLINFO_HEADER_OUT'] = false;    // https://bugs.php.net/bug.php?id=65348
            $options['CURLOPT_STDERR'] = \fopen('php://temp', 'rw');
        }
        if (!empty($options['CURLOPT_PROXY']) && \in_array($urlParts['host'], array('127.0.0.1','localhost'))) {
            $this->debug->log('not using proxy for localhost');
            $options['CURLOPT_PROXY'] = false;  // formerly set to null... which no longer works
        }
        if (empty($options['CURLOPT_PROXY']) && \getenv('http_proxy')) {
            $this->debug->log('http_proxy env variable is set.... setting NO_PROXY env variable');
            \putenv('NO_PROXY='.$urlParts['host']);
        }
        $this->debug->groupEnd();
        return $options;
    }

    /**
     * Get defautl cURL options
     *
     * @param string $url url
     *
     * @return array
     */
    protected function curlOptionsDefault($url)
    {
        $this->debug->groupCollapsed(__METHOD__);
        $opts = array(
            // 'CURLOPT_VERBOSE'    => 1,
            // 'CURLOPT_STDERR'     => fopen(dirname($_SERVER['SCRIPT_FILENAME']).'/stderr.txt','a'),
            // 'CURLOPT_COOKIEJAR'  => 'c:/shazbot.txt',    // broken was only grabbing 2 of 3 cookies
            // 'CURLOPT_COOKIEFILE' => 'c:/shazbot.txt',
            // 'CURLOPT_CAINFO'     => '/usr/local/apache/htdocs/ca-bundle.cert',
            'curl_getinfo'          => false,   // whether to display
            'verbose'               => false,
            'CURLINFO_HEADER_OUT'   => true,
            'CURLOPT_FOLLOWLOCATION'=> false,   // will follow manualy because of the cookie problem
            'CURLOPT_HEADER'        => true,    // return header (to grab cookies n whatnot)
            'CURLOPT_IPRESOLVE'     => CURL_IPRESOLVE_V4,
            'CURLOPT_URL'           => $url,
            'CURLOPT_HTTPHEADER'    => array(),
            'CURLOPT_REFERER'       => Html::getSelfUrl(array(), array('fullUrl'=>true,'chars'=>false)),
            'CURLOPT_RETURNTRANSFER'=> true,    // return string rather than echo
            'CURLOPT_SSL_VERIFYPEER'=> false,
            'CURLOPT_USERAGENT'     => !empty($_SERVER['HTTP_USER_AGENT'])
                ? $_SERVER['HTTP_USER_AGENT']
                : 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
        );
        $opts = \array_merge($opts, $this->cfg['curl_opts']);
        $proxy = \getenv('http_proxy');
        if ($proxy) {
            $this->debug->log('http_proxy env var set...setting default PROXY, CURLOPT_PROXYUSERPWD, and PROXYAUTH');
            $pup = \parse_url($proxy);
            $opts['CURLOPT_PROXY'] = $pup['host'];
            if (!empty($pup['port'])) {
                $opts['CURLOPT_PROXY'] .= ':'.$pup['port'];
            }
            if (!empty($pup['user'])) {
                $opts['CURLOPT_PROXYUSERPWD'] = $pup['user'];
                if (!empty($pup['pass'])) {
                    $opts['CURLOPT_PROXYUSERPWD'] .= ':'.$pup['pass'];
                }
            }
            $opts['CURLOPT_PROXYAUTH'] = CURLAUTH_BASIC;    // | CURLAUTH_NTLM;
        }
        // $this->debug->log('opts', $opts);
        $this->debug->groupEnd();
        return $opts;
    }

    /**
     * Normalize cURL options
     *
     * @param array $opts options
     *
     * @return array
     */
    protected function curlOptionsNormalize($opts)
    {
        $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
        $optionTrans = array(
            'follow'        => 'CURLOPT_FOLLOWLOCATION',
            'proxy_userpwd' => 'CURLOPT_PROXYUSERPWD',
            // 'timeout'    => array('CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT'),
            'timeout'       => 'CURLOPT_CONNECTTIMEOUT',
        );
        if (!empty($opts['cookies'])) {
            $cookieString = '';
            foreach ($opts['cookies'] as $key => $value) {
                $cookieString .= $key.'='.$value.'; ';
            }
            $opts['CURLOPT_COOKIE'] = \substr($cookieString, 0, -2);    // take off trailing ';'.
            unset($opts['cookies']);
        }
        if (!empty($opts['post'])) {
            $opts['CURLOPT_POST'] = true;
            $opts['CURLOPT_POSTFIELDS'] = $opts['post'];    // array sets content-type multi-part
            unset($opts['post']);
        }
        $haveInt = false;
        foreach ($optionTrans as $k => $kna) {
            if (\array_key_exists($k, $opts)) {
                if (!\is_array($kna)) {
                    $kna = array($kna);
                }
                foreach ($kna as $kn) {
                    if (!\array_key_exists($kn, $opts)) {
                        $opts[$kn] = $opts[$k];
                    }
                }
                unset($opts[$k]);
            }
        }
        foreach ($opts as $k => $v) {
            if ($k == 'verbose') {
                continue;
            }
            if (\is_string($k) && \defined('CURLOPT_'.\strtoupper($k))) {
                ArrayUtil::keyRename($opts, $k, 'CURLOPT_'.\strtoupper($k));
            } elseif (\is_int($k)) {
                $haveInt = true;
            }
        }
        if ($haveInt) {
            // convert any integer consts to string so that may be merged with defaults
            $consts_all = \get_defined_constants(true);
            $consts_all = $consts_all['curl'];
            $consts = array();
            foreach ($consts_all as $k => $v) {
                unset($consts_all[$k]);
                if (\preg_match('/^CURLOPT_/', $k)) {
                    if (!isset($consts[$v])) {
                        $consts[$v] = $k;
                    } else {
                        if (!\is_array($consts[$v])) {
                            $consts[$v] = array( $consts[$v] );
                        }
                        $consts[$v][] = $k;
                    }
                }
            }
            #$this->debug->log('consts', $consts);
            foreach ($opts as $k => $v) {
                if (\is_int($k)) {
                    if (isset($consts[$k])) {
                        $k_new = \is_array($consts[$k])
                            ? $consts[$k][0]
                            : $consts[$k];
                        unset($opts[$k]);
                        $opts[$k_new] = $v;
                    } else {
                        $this->debug->warn('no such curl opt!', $k);
                    }
                }
            }
        }
        if (!empty($opts['CURLOPT_COOKIEFILE'])) {
            // can't use curlopt_cookie if using curlopt_cookiefile
            unset($opts['CURLOPT_COOKIE']);
        }
        $this->debug->log('post normalize opts', $opts);
        $this->debug->groupEnd();
        return $opts;
    }

    /**
     * Set Curl Options
     *
     * @param resource $curl    curl handle
     * @param array    $options options array
     *
     * @return void
     */
    protected function curlSetOptions($curl, $options)
    {
        foreach ($options as $k => $v) {
            // $this->debug->log($k, $v);
            if (\is_string($k) && \defined($k)) {
                $k = \constant($k);
            }
            if (\is_string($v) && \defined($v)) {
                $v = \constant($v);
            }
            if (\is_null($v) || \is_string($v) && empty($v)) {
                unset($options[$k]);
            } elseif (\is_int($k)) {
                \curl_setopt($curl, $k, $v);
            }
        }
        if ($this->debug->getCfg('collect')) {
            $optionsDebug = array();
            foreach ($options as $k => $v) {
                if (\strpos($k, 'PWD')) {
                    $v = \preg_replace('/:.*$/', ':xxxxxx', $v);
                }
                $optionsDebug[$k] = array('value' => $v);
            }
            $this->debug->table('options', $optionsDebug);
        }
    }

    /**
     * Build return value from fetchResponse
     *
     * @return array|false
     */
    protected function curlGetReturn()
    {
        $follow = !isset($this->optionsPassed['CURLOPT_FOLLOWLOCATION'])
            || $this->optionsPassed['CURLOPT_FOLLOWLOCATION'];
        $headers = $this->fetchResponse['headers'];
        if ($follow && isset($headers['Location'])) {   // follow location
            $location = Html::getAbsUrl($this->options['CURLOPT_URL'], $headers['Location']);
            $this->debug->info('redirect', $location);
            if (\count(\array_keys($this->redirectHistory, $location)) > 1) {
                // a -> b -> a   is OK
                $this->debug->warn('redirect loop', $this->redirectHistory);
                $return = false;
            } else {
                if (\in_array($headers['Return-Code'], array(302,303))) {
                    $this->optionsPassed['CURLOPT_POSTFIELDS'] = null;
                    $this->optionsPassed['post'] = null;
                }
                $this->optionsPassed['cookies'] = $this->fetchResponse['cookies'];
                unset($this->optionsPassed['url']); // url used by scrape()
                unset($this->optionsPassed['CURLOPT_URL']);
                $return = $this->fetchCurl($location, $this->optionsPassed);
            }
        } else {
            $this->redirectHistory = array();
            if ($headers && $headers['Return-Code'] != 200 && $follow) {
                #$this->debug->log('fetchResponse', $this->fetchResponse);
                $return = false;
            } else {
                if (!empty($headers['Content-Encoding']) && $headers['Content-Encoding'] == 'gzip') {
                    $this->debug->info('decompressing gzip content');
                    $this->fetchResponse['body'] = Gzip::decompressString($this->fetchResponse['body']);
                }
                $return = $this->fetchResponse;
            }
        }
        return $return;
    }

    /**
     * Get gookie name/values
     *
     * @param array $headers header values
     *
     * @return array
     */
    private static function getCookies($headers)
    {
        $cookiesRaw = isset($headers['Set-Cookie'])
            ? (array) $headers['Set-Cookie']
            : array();
        $cookies = array();
        foreach ($cookiesRaw as $header) {
            $pairs = \explode(';', $header);
            $pair = \explode('=', $pairs[0]);
            $key = \array_shift($pair);
            $value = \array_shift($pair);
            $cookies[\urldecode($key)] = \urldecode($value);
        }
        return $cookies;
    }

    private function getOptions($optionsDefault, $options)
    {
        if (isset($options['proxy']) && $options['proxy'] === true) {
            unset($options['proxy']);   // unset so that default gets used
        }
        $options = \array_merge($optionsDefault, $options);
        if ($options['proxy']) {
            // split proxy param into proxy & proxy_userpwd
            $pup = \parse_url($options['proxy']);
            $options['proxy'] = $pup['host'].(!empty($pup['port']) ? ':'.$pup['port'] : '' );
            if (!empty($pup['user'])) {
                $options['proxy_userpwd'] = $pup['user'];
                if (!empty($pup['pass'])) {
                    $options['proxy_userpwd'] .= ':'.$pup['pass'];
                }
            }
        } else {
            unset($options['proxy']);
        }
        return $options;
    }

    private function getOptionsDefault()
    {
        $netCfg = Config::getCfg('Net');
        $optionsDefault = array(
            'proxy' => isset($netCfg['proxy'])
                ? $netCfg['proxy']
                : \getenv('http_proxy'), // false if not set
            'proxy_userpwd' => null,
            'timeout' => 10,    // \ini_get('default_socket_timeout'),   // connect timeout
        );
        return $optionsDefault;
    }
}
