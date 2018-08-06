<?php

if (!function_exists('_')) {
    /**
     * gettext() alias
     *
     * @param string $str string
     *
     * @return string
     */
    function _($str)
    {
        return $str;
    }
}

if (!function_exists('apache_request_headers')) {
    /**
     * Shim for the apache_request_headers function
     *
     * @return array
     */
    function apache_request_headers()
    {
        $headers = array();
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $k = strtr(substr($k, 5), '_', ' ');
                $k = ucwords(strtolower($k));
                $k = strtr($k, ' ', '-');
                $headers[$k] = $v;
            }
        }
        return $headers;
    }
}
