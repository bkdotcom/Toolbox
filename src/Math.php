<?php

namespace bdk;

/**
 * Math
 */
class Math
{

    /**
     * Ceiling to increment value
     * ie ceilToInc(11, 5)  // returns 15
     *
     * @param float   $val value to round
     * @param integer $inc increment
     *
     * @return integer
     */
    public static function ceilToInc($val, $inc)
    {
        if ($inc < 1) {
            $inc = 1;
        }
        return $inc * ceil($val / $inc);
    }

    /**
     * Round to increment
     * ie roundToInc(11, 5) // returns 10
     *
     * @param float   $val value to round
     * @param integer $inc increment
     *
     * @return integer
     */
    public static function roundToInc($val, $inc)
    {
        if ($inc < 1) {
            $inc = 1;
        }
        return $inc * round($val / $inc);
    }


    /**
     * This function expects an array of arrays
     * array(
     *     array(weight, value)
     *     array(weight, value)
     * )
     * weights should be integers
     *
     * It will return a randomly selected value.
     *
     * @param array $data array
     *
     * @return mixed
     */
    public static function weightedRand($data)
    {
        $weights = array_column($data, 0);
        $totalw = array_sum($weights);
        $rand   = rand(1, $totalw);
        $sum   = 0;
        foreach ($data as $array) {
            $weight = $array[0];
            $sum += $weight;
            if ($sum >= $rand) {
                $return  = $array[1];
                break;
            }
        }
        return $return;
    }
}
