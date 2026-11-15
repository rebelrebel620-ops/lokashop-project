<?php
namespace App\Services;
class CourierCapacity {
 public static function limit($courier):int {return ($courier->courier_type??'rider')==='driver'?3:20;}
}
