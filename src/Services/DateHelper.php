<?php
namespace App\Services;

class DateHelper
{
    public static function toTaiwanYear(string $isoDate): string
    {
        $ts = strtotime($isoDate);
        if ($ts === false) return $isoDate;
        $year = (int)date('Y', $ts) - 1911;
        return $year . date('md', $ts);
    }
}
