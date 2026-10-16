<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Municipalities {
 public static function normalize(string $name):string {$name=mb_strtolower(trim($name));$name=preg_replace('/^(?:science )?city of |^province of | city$/u','',$name);$name=preg_replace('/\bsta[.]?\s+/u','santa ',$name);$name=preg_replace('/\bsto[.]?\s+/u','santo ',$name);return preg_replace('/[^\p{L}\p{N}]+/u','',$name);}
 public static function key(string $province,string $city):string{return hash('sha256',self::normalize($province).'|'.self::normalize($city));}
 public static function address(int $user){return DB::table('addresses')->where('user_id',$user)->orderByDesc('is_default')->orderBy('id')->first();}
 public static function centerFor($a){if(!$a)return null;return DB::table('sorting_centers as c')->join('users as u','u.id','=','c.user_id')->where('c.municipality_key',self::key($a->province,$a->city))->where('u.active',1)->where('u.account_status','active')->where('u.approval_status','approved')->select('c.*')->first();}
 public static function matches($a,$b):bool{return $a&&$b&&self::key($a->province,$a->city)===self::key($b->province,$b->city);}
 public static function courier($u,$p,string $kind):void {
  if(!$u||$u->role!=='rider'||!$u->active||$u->account_status!=='active'||$u->approval_status!=='approved')Support::fail('rider_id','Choose an active approved courier.');
  $owner=DB::table('users')->where('id',$u->company_id)->where('role','sorting_center')->where('active',1)->where('account_status','active')->where('approval_status','approved')->first();if(!$owner)Support::fail('rider_id','The courier sorting center is unavailable.');
  $registered=DB::table('sorting_centers')->where('user_id',$u->company_id)->first();if(!$registered||!$registered->municipality_key)Support::fail('rider_id','Ask the logistics administrator to assign a service municipality to this sorting center.');
  if($kind==='transfer'){if($u->courier_type!=='driver')Support::fail('rider_id','Only logistics drivers may transfer between sorting centers.');return;}
  if($u->courier_type!=='rider')Support::fail('rider_id','Only motorcycle/tricycle riders may pick up or deliver to customers.');
  $o=DB::table('orders')->find($p->order_id);$address=$kind==='pickup'?self::address($o->seller_id):($o->address_snapshot?json_decode($o->address_snapshot):DB::table('addresses')->find($o->address_id));
  $c=DB::table('sorting_centers')->where('user_id',$u->company_id)->first();$own=self::address($u->id)??$c;
  if(!self::matches($own,$c)||!self::matches($own,$address))Support::fail('rider_id','Riders serve only their registered municipality. Use a driver for inter-center transfers.');
 }
}
