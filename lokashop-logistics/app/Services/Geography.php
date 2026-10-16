<?php
namespace App\Services;
class Geography {
 private static ?array $data=null;
 public static function data():array{return self::$data??=json_decode(file_get_contents(public_path('data/places.json')),true,512,JSON_THROW_ON_ERROR);}
 public static function validate(array $v):void {
  $d=self::data();$same=fn($a,$b)=>Municipalities::normalize($a)===Municipalities::normalize($b);
  $province=null;foreach($d['provinces'] as $x)if($same($x['name'],$v['province'])){$province=$x;break;}
  if(!$province)Support::fail('province','Choose a province from the address list.');
  if(!empty($v['region'])){$region=null;foreach($d['regions'] as $x)if($x['code']===$province['regionCode'])$region=$x;if(!$region||!$same($region['name'],$v['region']))Support::fail('region','Region and province do not match.');}
  $city=null;foreach($d['cities-municipalities'] as $x)if($x['provinceCode']===$province['code']&&$same($x['name'],$v['city'])){$city=$x;break;}
  if(!$city)Support::fail('city','Choose a city or municipality in the selected province.');
  foreach($d['barangays'] as $x)if(($x['cityCode']?:$x['municipalityCode'])===$city['code']&&$same($x['name'],$v['barangay']))return;
  Support::fail('barangay','Choose a barangay in the selected municipality.');
 }
}
