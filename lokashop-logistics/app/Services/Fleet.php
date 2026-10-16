<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Fleet {
 public static function validate(string $type,string $vehicle):void {$choices=$type==='rider'?['motorcycle','tricycle']:['4-wheel truck','6-wheel truck','8-wheel truck','10-wheel truck'];if(!in_array($vehicle,$choices,true))Support::fail('vehicle','Riders use motorcycles/tricycles. Drivers use 4-, 6-, 8- or 10-wheel trucks.');}
 public static function address(int $user,int $company):void {$a=Municipalities::address($company);if(!$a)Support::fail('company_id','Sorting center needs its registered address before adding couriers.');$u=DB::table('users')->find($user);$data=(array)$a;unset($data['id']);$data['user_id']=$user;$data['recipient']=$u->name;$data['phone']=$u->phone??$a->phone;$data['updated_at']=now();DB::table('addresses')->where('user_id',$user)->delete();DB::table('addresses')->insert($data);}
}
