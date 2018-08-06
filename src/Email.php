<?php

namespace bdk;

use bdk\ArrayUtil;
use bdk\Config;
use bdk\Php;

/**
 * Email
 */
class Email extends Config
{
    protected $debug;
    protected static $cfgStatic = array(
        'email' => array(
            'from' => '',   // set in constructor
        ),
        'debugMode' => false,
    );
    protected $cfg = array();
    public $debugResult = array();

    /**
     * Constructor
     *
     * @param array $cfg configuration
     */
    public function __construct($cfg = array())
    {
        $this->debug = \bdk\Debug::getInstance();
        self::$cfgStatic['email']['from'] = isset($_SERVER['SERVER_ADMIN'])
            ? $_SERVER['SERVER_ADMIN']
            : ini_get('sendmail_from');
        // $defaultCfg = Config::get('Email');
        /*
        if ($defaultCfg) {
            $this->cfg = array_merge($this->cfg, $defaultCfg);
        }
        */
        $this->cfg = array_merge(self::$cfgStatic, $cfg);
    }

    /**
     * Send an email
     *
     * @param array $headers headers
     *
     * @return boolean
     */
    public function send($headers)
    {
        $this->debug->groupCollapsed(__METHOD__);
        // $this->debug->log('headers', $headers);
        $return = false;
        self::normalizeHeaders($headers, $this->cfg['email']['from']);
        if (!empty($headers['Attachments'])) {
            $headers['MIME-Version'] = '1.0';
            if (!empty($headers['Body'])) {
                array_unshift($headers['Attachments'], array('body'=>$headers['Body']));
                if (!empty($headers['Content-Type'])) {
                    $headers['Attachments'][0]['content_type'] = $headers['Content-Type'];
                }
            }
            list($headers['Content-Type'], $headers['Body']) = $this->buildBody($headers['Attachments']);
            unset($headers['Attachments']);
        }
        $this->debugHeaders($headers);
        $headersAddStr = self::buildAdditionalHeadersString($headers);
        $params = array($headers['To'], $headers['Subject'], $headers['Body'], $headersAddStr);
        if (!ini_get('safe_mode')) {
            $params[] = self::buildAdditionalParamsString($headers);
        }
        if ($headers['To']) {
            if ($this->cfg['debugMode']) {
                $this->debug->warn('debugMode: email not sent');
                // don't sent email when debug=true
                $return = true;
                $this->debugResult = $params;
            } else {
                $return = call_user_func_array('mail', $params);
            }
        }
        $this->debug->log('Email was '.(($return)?'':'NOT').' successful');
        $this->debug->groupEnd();
        return $return;
    }

    /**
     * [getAttachmentArray description]
     *
     * @param array $attachment attachment
     *
     * @return array
     */
    protected static function getAttachmentArray($attachment)
    {
        $default = array(
            'content_type'  => 'text/plain',    // 'application/octet-stream',
            'disposition'   => 'attachment',    // inline
            'body'          => '',
            'file'          => null,
            'filename'      => null,
            'content_id'    => null,
        );
        $eol = "\r\n";
        $attachment = array_merge($default, $attachment);
        if (isset($attachment['file'])) {
            $attachment['body'] = file_get_contents($attachment['file']);
            if (empty($attachment['filename'])) {
                $attachment['filename'] = basename($attachment['file']);
            }
            $attachment['content_transfer_encoding'] = 'base64';
            $attachment['body'] = chunk_split(base64_encode($attachment['body']), 68, $eol);
            $attachment['body'] = substr($a['body'], 0, -strlen($eol));  // remove final $eol
            $attachment['file'] = null;
        }
        return $attachment;
    }

    /**
     * Build email body
     *
     * @param array $attachments an array detailing the type of attachment
     *
     * @return   array
     * @internal
     */
    protected function buildBody($attachments = array())
    {
        $this->debug->groupCollapsed(__METHOD__);
        #$this->debug->log('attachments',$attachments);
        $body = '';
        $eol = "\r\n";
        $mimeBoundary = '==Multipart_Boundary_x'.substr(md5(uniqid(rand(), true)), 0, 10).'x';
        $multipartType = '';
            // 'multipart/mixed';
            // 'multipart/alternative';
            // 'multipart/related';
        $contentTypes = array();
        $isSelfCalled = Php::getCallerInfo(false) == __CLASS__.'::'.__FUNCTION__;
        $this->debug->log('isSelfCalled', $isSelfCalled);
        for ($k=0; $k<count($attachments); $k++) {
            $a = $attachments[$k];
            $keys = array_keys($a);
            if ($k==0 && is_string($a)) {
                $multipartType = $a;
                continue;
            } elseif ($keys[0] == '0') {
                $this->debug->log('new array');
                list($type, $content) = $this->buildBody($a);
                $body .= '--'.$mimeBoundary.$eol;
                $body .= 'Content-Type: '.$type.$eol.$eol;
                $body .= $content;
                continue;
            }
            $a = getAttachmentArray($a);
            // .'Content-Type: text/plain; charset="ISO-8859-1"'.$eol
            // .'Content-Transfer-Encoding: 7bit'.$eol
            if (empty($multipartType)) {
                if ($a['content_type'] == 'text/html') {
                    $this->debug->log('html');
                    $multipartType = 'multipart/alternative';
                    if (strpos($a['body'], 'cid:')) {
                        $this->debug->log('found "cid:"');
                        if (in_array('text/plain', $contentTypes)) {
                            $this->debug->log('prior alternative');
                            $attachments[$k] = $a;
                            $attachments[] = array_splice($attachments, $k);
                            $k--;
                            continue;
                        } else {
                            $multipartType = 'multipart/related';
                        }
                    }
                }
            }
            if (!in_array($a['content_type'], $contentTypes)) {
                $contentTypes[] = $a['content_type'];
            }
            $body .= '--'.$mimeBoundary.$eol;
            $body .= 'Content-Type: '.$a['content_type'].';'.( !empty($a['filename']) ? ' name="'.$a['filename'].'";' : '' ).$eol;  // charset="ISO-8859-1";
            if (!empty($a['filename'])) {
                $body .= 'Content-Disposition: '.$a['disposition'].'; filename="'.$a['filename'].'"'.$eol;
            }
            if (!empty($a['content_id'])) {
                $body .= 'Content-ID: <'.$a['content_id'].'>'.$eol;
            }
            if (!empty($a['content_transfer_encoding'])) {
                $body .= 'Content-Transfer-Encoding: '.$a['content_transfer_encoding'].$eol;
            }
            $body .= $eol;
            $body .= $a['body'].$eol.$eol;
        }
        $body .= '--'.$mimeBoundary.'--'.$eol;
        #$this->debug->log('contentTypes', $contentTypes);
        if (!$isSelfCalled) {
            $body = ''
                .'This is a multi-part message in MIME format.'.$eol
                .$eol
                .$body;
        }
        if (empty($multipartType)) {
            $multipartType = 'multipart/mixed';
        }
        $multipartType = $multipartType.'; boundary="'.$mimeBoundary.'";';
        $this->debug->groupEnd();
        return array($multipartType, $body);
    }

    /**
     * Normalize Headers
     *
     * @param array $headers     headers
     * @param mixed $defaultFrom from address(es) (string or array)
     *
     * @return void
     */
    protected static function normalizeHeaders(&$headers, $defaultFrom = null)
    {
        $headers = array_merge(array(
            'to' => '',     // array or string
            'subject' => '',
            'from' =>  $defaultFrom,
            'body' => '',  // html   if empty, will use template & substitutions
            'content-type' => 'text/html',
            'reply-to' => null,
        ), $headers);
        foreach ($headers as $k => $v) {
            $k_new = preg_replace_callback('/(\w*)/', function ($matches) {
                return ucfirst($matches[1]);
            }, strtolower(strtr($k, '_', '-')));
            ArrayUtil::keyRename($headers, $k, $k_new);
        }
        // $this->debug->log('headers', $headers);
        foreach ($headers as $k => $v) {
            if (in_array($k, array('To','Cc','Bcc','From'))) {
                $headers[$k] = self::addressBuildString($v);
            }
        }
    }

    /**
     * [buildAdditionalParamsString description]
     *
     * @param array $headers headers
     *
     * @return string
     */
    protected static function buildAdditionalParamsString(&$headers)
    {
        $paramsAddStr = '';
        if (!empty($headers['From']) && !isset($_SERVER['WINDIR'])) {
            $fromAddress = preg_match('/<(.*?)>/', $headers['From'], $matches)
                ? $matches[1]
                : $headers['From'];
            if (( $sendmailPath = ini_get('sendmail_path') ) && !strpos($sendmailPath, $fromAddress)) {
                $paramsAddStr = '-f '.$fromAddress;
                // $this->debug->log('sendmail_path', $sendmailPath.' '.$paramsAddStr);
            }
        }
        return $paramsAddStr;
    }

    /**
     * [buildAdditionalHeadersString description]
     *
     * @param array $headers headers
     *
     * @return string
     */
    protected static function buildAdditionalHeadersString(&$headers)
    {
        $headersAddStr = '';
        $eol = "\r\n";
        foreach ($headers as $k => $v) {
            if (in_array($k, array('To','Subject','Body','Attachments'))) {
                continue;
            }
            if (empty($v)) {
                continue;
            }
            if ($k == 'From' && isset($_SERVER['WINDIR'])) {
                $fromAddress = preg_match('/<(.*?)>/', $v, $matches)
                    ? $matches[1]
                    : $v;
                // $this->debug->log('setting sendmail_from to '.$fromAddress);
                ini_set('sendmail_from', $fromAddress);
            }
            $headersAddStr .= $k.': '.$v.$eol;
        }
        $headersAddStr = substr($headersAddStr, 0, -strlen($eol));  // remove final $eol;
        return $headersAddStr;
    }

    /**
     * [debugHeaders description]
     *
     * @param array $headers headers
     *
     * @return void
     */
    protected function debugHeaders(&$headers)
    {
        if (!$this->debug->getCfg('collect')) {
            return;
        }
        $eol = "\r\n";
        $strDebug = '';
        foreach ($headers as $k => $v) {
            if (in_array($k, array('Attachments', 'Body'))) {
                continue;
            }
            if (empty($v)) {
                continue;
            }
            $strDebug .= '<u>'.$k.'</u>: '.htmlspecialchars($v).$eol;
        }
        $strDebug .= $eol.htmlspecialchars(
            preg_replace('/(Content-Transfer-Encoding:\s+base64'.$eol.$eol.').+'.$eol.$eol.'/s', '$1removed for debug output'.$eol.$eol, $headers['Body'])
        );
        $this->debug->log(
            'Email:<br />'
            .'<pre style="margin-left:2em; padding:.25em; border:#999 solid 1px; background-color:#DDD; color:#000;">'
            .$strDebug
            .'</pre>'
        );
    }

    /**
     * Get address array from passed address string or addresses
     *
     * @param mixed $addresses address / addresses
     *
     * @return array  (email => name, x => email)
     */
    public static function addressesGet($addresses)
    {
        // $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
        $return = array();
        if (!is_array($addresses)) {
            $addresses = self::addressParse($addresses);
        }
        foreach ($addresses as $k => $v) {
            if (is_array($v)) {
                // parsed address
                if (!empty($v['name'])) {
                    $return[ $v['email'] ] = $v['name'];
                } else {
                    $return[] = $v['email'];
                }
            } elseif (is_int($k)) {
                $v = self::addressParse($v);
                $v = array_shift($v);
                // $this->debug->log($k, $v);
                if (!empty($v['name'])) {
                    $return[ $v['email'] ] = $v['name'];
                } else {
                    $return[] = $v['email'];
                }
            } else {
                // email => name
                $return[$k] = $v;
            }
        }
        // $this->debug->groupEnd();
        return $return;
    }

    /**
     * Returns email address string
     *
     * @param mixed $addresses addresses string or array
     *
     * @return string
     */
    public static function addressBuildString($addresses)
    {
        $addresses = self::addressesGet($addresses);
        $addressesNew = array();
        foreach ($addresses as $k => $v) {
            $name = null;
            if (is_int($k)) {
                $address = $v;
            } else {
                $name = $v;
                $address = $k;
            }
            if (!isset($_SERVER['WINDIR']) && $name) {
                if (strpos($name, ',')) {
                    $name = addcslashes($name, '()');
                    $name = '"'.$name.'"';
                }
                $address = $name.' <'.$address.'>';
            }
            $addressesNew[] = $address;
        }
        return implode(', ', $addressesNew);
    }

    /**
     * parse address string
     *
     * @param string $addressString rfc-822 address list
     *
     * @return array (name & email)
     * @see    http://stackoverflow.com/questions/6609195/parse-rfc-822-compliant-addresses-in-a-to-header
     */
    public static function addressParse($addressString)
    {
        // $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
        // $this->debug->log('addressString', $addressString);
        $pattern = '/^(?:"?((?:[^"\\\\]|\\\\.)+)"?\s)?'             // name
            .'<?([a-z0-9._%-]+@[a-z0-9.-]+\\.[a-z]{2,4})>?$/i';     // email
        $addresses = str_getcsv($addressString);
        // $this->debug->log('addresses', $addresses);
        $result = array();
        foreach ($addresses as $address) {
            $address = trim($address);
            if (preg_match($pattern, $address, $matches)) {
                $item = array();
                if ($matches[1] != '') {
                    $item['name'] = stripcslashes($matches[1]);
                }
                $item['email'] =  $matches[2];
                $result[] = $item;
            }
        }
        // $this->debug->groupEnd();
        return $result;
    }

    /**
     * validate an email addrses
     *
     * @param string $email email address
     *
     * @return string|boolean returns false if invalid
     */
    public static function addressValidate($email)
    {
        // $this->debug->groupCollapsed(__CLASS__.'->'.__FUNCTION__);
        $return = false;
        $regex = '/^'
            .'[a-z0-9_]+([_\.-][a-z0-9]+)*'     // user
            .'@'
            .'([a-z0-9]+([\.-][a-z0-9]+)*)+'    // domain
            .'(\.[a-z]{2,})'                    // sld, tld
            .'$/i';
        $email = trim($email);
        if (preg_match($regex, $email, $matches)) {
            // $this->debug->log('properly formatted');
            $hostname   = strtolower($matches[2].$matches[4]);
            $ipaddress  = gethostbyname($hostname);     // A record
            // $this->debug->log('hostname', $hostname);
            // $this->debug->log('ipaddress', $ipaddress);
            $return = $email;
            if ($ipaddress != $hostname) {
                // $this->debug->log('A record', $ipaddress);
            } elseif ($mxrecord = getmxrr($hostname, $mxhosts)) {
                // $this->debug->log('getmxrr('.$hostname.')', $mxrecord, $mxhosts);
            } elseif (strpos($_SERVER['SERVER_NAME'], $hostname)) {
                // $this->debug->log('email domain matches server domain');
            } else {
                // $this->debug->log('warn', 'unable to verify');
                $return = false;
            }
        }
        // $this->debug->groupEnd();
        return $return;
    }
}
