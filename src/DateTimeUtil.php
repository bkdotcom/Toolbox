<?php
/**
 * Date/Time related methods
 *
 * Notes:
 *  unix timestamps are always GMT
 *      funcs that create/format timestamp take local timezone in to consideration
 *  date, mktime, strtotime, etc have 32-bit limitation
 *      valid range PHP >= 5.1 : Fri, 13 Dec 1901 20:45:54 GMT - Tue, 19 Jan 2038 03:14:07 GMT
 *      valid range PHP < 5.1 : 01-01-1970 00:00:00 GMT - Tue, 19 Jan 2038 03:14:07 GMT
 *      date & getdate
 *          do not throw error or return false when passed a timestamp that is out of range,
 *          rather, the date is wrapped for unexpected results
 *      mktime & strtotime
 *          return false or -1 depending on version of php
 *  DateTime class (PHP >= 5.2) does not have 32-bit limitation
 *      DateTime::add and DateTime:sub (php >= 5.3) are capable of doing arithmetic outside the 32-bit range
 */

namespace bdk;

/**
 * DateTime
 */
class DateTimeUtil
{

    // private $debug = null;
    // private $sysInfo;

    /**
     * Convert datetime string to timestamp
     *
     * @param mixed $datetime DateTime, string, or integer
     *
     * @return integer timestamp
     */
    public static function datetimeToTs($datetime)
    {
        /*
        $keys = array('ye', 'mo', 'da', 'hr', 'mi', 'se');
        $values = \sscanf($datetime, '%4s%2s%2s%2s%2s%2s');
        $parts = array_combine($keys, $values)
        return \mktime($parts['hr'], $parts['mi'], $parts['se'], $parts['mo'], $parts['da'], $parts['ye']);
        */
        $datetime = self::getDateTime($datetime);
        return (int) $datetime->format('U');
    }

    /**
     * Converts format used by date() to one that can be used by strftime()
     *
     * @param string $dateFormat format used by date()
     *
     * @return string
     * @link   http://www.php.net/manual/en/function.strftime.php
     */
    public static function dateToStrftime($dateFormat)
    {
        $caracs = array(
            // Day - no strf eq : S
            'd' => '%d', 'D' => '%a', 'j' => '%e', 'l' => '%A', 'N' => '%u', 'w' => '%w', 'z' => '%j',
            // Week - no date eq : %U, %W
            'W' => '%V',
            // Month - no strf eq : n, t
            'F' => '%B', 'm' => '%m', 'M' => '%b',
            // Year - no strf eq : L; no date eq : %C, %g
            'o' => '%G', 'Y' => '%Y', 'y' => '%y',
            // Time - no strf eq : B, G, u; no date eq : %r, %R, %T, %X
            'a' => '%P', 'A' => '%p', 'g' => '%l', 'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S',
            // Timezone - no strf eq : e, I, P, Z
            'O' => '%z', 'T' => '%Z',
            // Full Date / Time - no strf eq : c, r; no date eq : %c, %D, %F, %x
            'U' => '%s'
        );
        return \strtr((string) $dateFormat, $caracs);
    }

    /**
     * Equivalent to PHP's getdate()...
     * Uses DateTime class to avoid 32-bit limitation
     * Accepts DateTime, string, or Int
     *
     * @param mixed $ts timestamp
     *
     * @return array
     */
    public static function getdate($ts)
    {
        $datetime = self::getDateTime($ts);
        return array(
            'seconds'   => $datetime->format('s'),  // 00-59
            'minutes'   => $datetime->format('i'),  // 00-59
            'hours'     => $datetime->format('H'),  // 00-23
            'mday'      => (int) $datetime->format('d'),    // 1-31
            'wday'      => (int) $datetime->format('j'),    // 0-6
            'mon'       => (int) $datetime->format('n'),    // 1-12
            'year'      => $datetime->format('Y'),  // 4-digit year
            'yday'      => (int) $datetime->format('z'),    // 0-365
            'weekday'   => $datetime->format('l'),  // Sunday - Saturday
            'month'     => $datetime->format('F'),  // January - December
            '0'         => (int) $datetime->format('U'),
        );
    }

    /**
     * Return a datetime obj
     *
     * @param mixed $datetime DateTime, DatetimeImmutable, int, or string
     *
     * @return DateTime
     */
    public static function getDateTime($datetime = null)
    {
        if ($datetime instanceof \DateTime) {
            return clone $datetime;
        }
        if ($datetime instanceof \DateTimeImmutable) {
            return new \DateTime($datetime->format(\DateTime::ATOM));
        }
        if (\is_string($datetime)) {
            if (\is_numeric($datetime)) {
                /*
                    check if datetime is an int of the the form YYYYMMDD or YYYYMMDDHHMMSS
                    Debug::_log('datetime', $datetime)
                */
                $isTs = !preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])[0-3][0-9](([01]\d|2[0-3])\d[0-5]\d[0-5]\d)?$/', $datetime);
                if ($isTs) {
                    $datetime = '@'.$datetime;
                }
            }
            Debug::_log('datetime', $datetime);
            return new \DateTime($datetime);
        }
        if (\is_int($datetime) || \is_float($datetime)) {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp($datetime);
            return $dateTime;
        }
        return new \DateTime();
    }

    /**
     * Given passed datetime return DateTime of next business day
     *
     * @param mixed $datetime DateTime, string, or timestamp
     * @param array $holidays array()   Optional array of dates (formatted yyyymmdd) that will be skipped
     *
     * @return DateTime
     */
    public static function getNextBusinessDay($datetime, $holidays = array())
    {
        $dateParts = self::getdate($datetime);
        // Add 1 day to begin with
        $ts = \mktime(0, 0, 0, $dateParts['mon'], $dateParts['mday']+1, $dateParts['year']);
        $goodDate = false;
        while (!$goodDate) {
            $dateParts = \getdate($ts);
            if ($dateParts['weekday'] == 'Saturday') {
                // Add 2 days
                $ts = \mktime(0, 0, 0, $dateParts['mon'], $dateParts['mday']+2, $dateParts['year']);
            } elseif ($dateParts['weekday'] == 'Sunday') {
                // Add 1 day
                $ts = \mktime(0, 0, 0, $dateParts['mon'], $dateParts['mday']+1, $dateParts['year']);
            } elseif (\in_array(\date('Ymd', $ts), $holidays)) {
                // Add 1 day
                $ts = \mktime(0, 0, 0, $dateParts['mon'], $dateParts['mday']+1, $dateParts['year']);
            } else {
                $goodDate = true;
            }
        }
        return self::getDateTime($ts);
    }

    /**
     * find the start/end of the hour,day,week,month, or year that contains passed date/timestamp
     *   also finds passed date/timestamp +/- same period
     *
     * @param mixed $datetime DateTime, int timestamp, or string date
     * @param array $opts     options
     *
     * @return boolean|array false on failure, array on success
     */
    public static function getRange($datetime, $opts = array())
    {
        $datetime = self::getDateTime($datetime);
        $opts = \array_merge(array(
            'range'     => 'day',   // hour, day, week, month, or year
            'format'    => null,
            'weekStartDay'=> 0,
        ), $opts);
        $return = false;
        switch ($opts['range']) {
            case 'hour':
                $strStart = $datetime->format('Y-m-d H:00:00');
                $dateInterval = new \DateInterval('PT1H');
                break;
            case 'day':
                $strStart = $datetime->format('Y-m-d');
                $dateInterval = new \DateInterval('P1D');
                break;
            case 'week':
                $days =  $datetime->format('w') - $opts['weekStartDay'];
                if ($days < 0) {
                    $days = $days + 7;
                }
                $dtTemp = clone $datetime;
                $dtTemp->setTime(0, 0)->sub(new \DateInterval('P'.$days.'D'));
                $strStart = $dtTemp->format('Y-m-d');
                $dateInterval = new \DateInterval('P1W');
                break;
            case 'month':
                $strStart = $datetime->format('Y-m-01');
                $dateInterval = new \DateInterval('P1M');
                break;
            case 'year':
                $strStart = $datetime->format('Y-01-01');
                $dateInterval = new \DateInterval('P1Y');
                break;
        }
        $dtStart = new \DateTime($strStart);
        $dtStartAtom = $dtStart->format(\DateTime::ATOM);
        /*
            DateTimeImmutable           is php 5.5
            (clone $datetime)->add()    is a php 7 thing
        */
        $return = array(
            'passed'=> $datetime,
            'start' => $dtStart,
            'end'   => (new \DateTime($dtStartAtom))
                ->add($dateInterval)
                ->sub(new \DateInterval('PT1S')),
            'prev'  => (new \DateTime($dtStartAtom))->sub($dateInterval),
            'next'  => (new \DateTime($dtStartAtom))->add($dateInterval),
            'minus' => (new \DateTime($datetime->format(\DateTime::ATOM)))->sub($dateInterval),
            'plus'  => (new \DateTime($datetime->format(\DateTime::ATOM)))->add($dateInterval),
        );
        if ($opts['range'] == 'month') {
            if ($return['plus']->format('j') !== $datetime->format('j')) {
                $return['plus']->modify('last day of last month');
            }
            if ($return['minus']->format('j') !== $datetime->format('j')) {
                $return['minus']->modify('last day of last month');
            }
        }
        return \array_map(function ($datetime) use ($opts) {
            if (!$opts['format']) {
                return $datetime;
            }
            $val = $datetime->format($opts['format']);
            if ($opts['format'] == 'U') {
                $val = (float) $val;
            }
            return $val;
        }, $return);
    }

    /**
     * Return a "3 hours ago" type string for passed timestamp
     *
     * @param mixed $datetime     DateTime, string, or timestamp
     * @param mixed $datetimeFrom (default = now) DateTime, string, or timestamp
     *
     * @return string
     */
    public static function getRelTime($datetime, $datetimeFrom = null)
    {
        $ret = '';
        $dateTime = self::getDateTime($datetime);
        $dateTimeFrom = self::getDateTime($datetimeFrom);
        $dateTimeSameDay = clone $dateTimeFrom;
        $dateTimeSameDay->setTime(0, 0, 0);
        $diffSec = $dateTimeFrom->getTimestamp() - $dateTime->getTimestamp();
        if ($dateTime > $dateTimeFrom) {
            // Future
            $dateTimeTest = clone $dateTimeSameDay;
            if ($dateTime < $dateTimeTest->modify('tomorrow')) {
                $ret = 'Today at '.$dateTime->format('g:ia');
            } elseif ($dateTime < $dateTimeTest->modify('+2 days')) {
                $ret = 'Tomorrow at '.$dateTime->format('g:ia');
            } else {
                $ret = $dateTime->format('F jS');
            }
        } elseif ($diffSec < 60) {
            // within a min
            $ret = 'Just now';
        } elseif ($diffSec < 3600) {
            // within an hour
            $ret = \round(($diffSec)/60) .' min ago';
        } elseif ($diffSec < 3600*6) {
            // within 6 hours
            $hrs = \round(($diffSec)/3600);
            $ret = $hrs.' '.Str::plural($hrs, 'hour').' ago';
        } elseif ($dateTime > $dateTimeSameDay) {             // since midnight so it's today
            $ret = 'Today at '.$dateTime->format('g:ia');
        } elseif ($dateTime->getTimestamp() >= $dateTimeSameDay->getTimestamp() - 86400) {
            $ret = 'Yesterday at '.$dateTime->format('g:ia');
        } elseif ($dateTime->getTimestamp() >= $dateTimeSameDay->getTimestamp() - 86400*7) {
            $ret = $dateTime->format('l \a\t g:ia');
        } elseif ($dateTime->getTimestamp() > \mktime(0, 0, 0, 1, 1)) {// since 1st Jan so it's this year
            $ret = $dateTime->format('F jS');
        } else {                                // last year..
            $ret = $dateTime->format('F jS, Y');
        }
        $ret = \str_replace(' at 12:00am', '', $ret);
        return $ret;
    }

    /**
     * Equivalent (almost) to mktime()...  uses DateTime class to avoid 32-bit limitation
     *  difference:  year is always assumed to be 4-digit
     *  to pass a 2-digit year, pass it as a string
     *
     * @param integer     $hr hour
     * @param integer     $mi min
     * @param integer     $se sec
     * @param integer     $mo month
     * @param integer     $da day
     * @param integer|str $ye year
     *
     * @return integer|float $timestamp
     */
    public static function mktime($hr = null, $mi = null, $se = null, $mo = null, $da = null, $ye = null)
    {
        $datetime = new \DateTime('now');
        $valsDefault = array(
            'hr'    => $datetime->format('H'),
            'mi'    => $datetime->format('i'),
            'se'    => $datetime->format('s'),
            'mo'    => $datetime->format('m'),
            'da'    => $datetime->format('d'),
            'ye'    => $datetime->format('Y'),
        );
        $intervalMap = array(
            'mo'    => 'P%sM',
            'da'    => 'P%sD',
            'hr'    => 'PT%sH',
            'mi'    => 'PT%sM',
            'se'    => 'PT%sS',
        );
        $vals = array();
        $args = \func_get_args();
        foreach (\array_keys($valsDefault) as $i => $k) {
            $vals[$k] = isset($args[$i])
                ? $args[$i]
                : $valsDefault[$k];
        }
        if (\is_string($vals['ye']) && \strlen($vals['ye']) < 3 && $vals['ye'] >= 0) {
            /*
                0-69 map to 2000-2069
                70-100 map to 1970-2000.
            */
            if ($vals['ye'] == 0) {
                $vals['ye'] = 2000;
            } elseif ($vals['ye'] < 70) {
                $vals['ye'] += 2000;
            } else {
                $vals['ye'] += 1900;
            }
        }
        // set the datetime to the very begining of passed year
        $datetime->setDate($vals['ye'], 1, 1);
        $datetime->settime(0, 0, 0);
        foreach ($intervalMap as $k => $is) {
            if (\in_array($k, array('mo', 'da'))) {
                // we started on Jan 1st
                $vals[$k]--;
            }
            if ($vals[$k] == 0) {
                continue;
            } elseif ($vals[$k] < 0) {
                $is = \sprintf($is, \abs($vals[$k]));
                $di = new \DateInterval($is);
                $datetime->sub($di);
            } else {
                $is = \sprintf($is, $vals[$k]);
                $di = new \DateInterval($is);
                $datetime->add($di);
            }
        }
        return $datetime;
    }

    /**
     * Get date/time related system info
     *
     * @return array
     */
    /*
    public static function sysInfo()
    {
        $isX64 = \defined('PHP_INT_SIZE') && PHP_INT_SIZE == 8;
        return array(
            '64bit'     => $isX64,
            'tsMin'     => $isX64
                            ? null
                            : ( \version_compare(PHP_VERSION, '5.1', '>=')
                                    ? \pow(-2, 31)+2
                                    : 0
                                ),
            'tsMax'     => $isX64
                            ? null
                            : \pow(2, 31)-1
        );
    }
    */

    /**
     * Equivalent to strtotime()...  attempts to use DateTime class to avoid 32-bit limitation
     *
     * @param string  $str     date/time
     * @param integer $tsStart relative timestamp
     *
     * @return integer|float $timestamp
     */
    /*
    public static function strtotime($str, $tsStart = null)
    {
        if ($tsStart !== null) {
            $datetimeStart = self::getDateTime($tsStart);
            $datetime = $datetimeStart->modify($str);
        } else {
            $datetime = new \DateTime($str);
        }
        return \floatval($datetime->format('U'));
    }
    */
}
