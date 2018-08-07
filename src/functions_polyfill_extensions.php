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
        return \bdk\Debug\Utilities::getAllHeaders();
    }
}

if (!function_exists('getallheaders')) {
    /**
     * Shim for the getallheaders function (alias of apache_request_headers)
     *
     * @return array
     */
    function getallheaders()
    {
        return \bdk\Debug\Utilities::getAllHeaders();
    }
}
