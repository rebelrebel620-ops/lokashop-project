<?php
namespace App\Services;
class CodAmount {
 public static function round($amount): float {return round(Support::money($amount),0,PHP_ROUND_HALF_UP);}
 public static function format($amount): string {$n=Support::money($amount);return number_format($n,$n==self::round($n)?0:2);}
}
