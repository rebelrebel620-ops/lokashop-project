<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class AreaRouting {
 public static function address($p){$o=DB::table('orders')->find($p->order_id);return $o->address_snapshot?json_decode($o->address_snapshot):DB::table('addresses')->find($o->address_id);}
 public static function areas($p,string $kind='delivery'){
  $o=DB::table('orders')->find($p->order_id);$a=$kind==='pickup'?Municipalities::address($o->seller_id):self::address($p);
  return DB::table('delivery_areas')->where('center_id',$p->center_id)->orderBy('id')->get()->filter(fn($x)=>Municipalities::matches($x,$a)&&Municipalities::normalize((string)$x->barangay)===Municipalities::normalize((string)($a->barangay??''))&&trim((string)$x->barangay)!=='')->values();
 }
 public static function riders($p,string $kind='delivery',bool $available=true){
  $areas=self::areas($p,$kind);$company=DB::table('sorting_centers')->where('id',$p->center_id)->value('user_id');
  $q=DB::table('users')->whereIn('id',$areas->pluck('rider_id')->filter())->where('company_id',$company)->where('role','rider')->where('courier_type','rider')->where('active',1)->where('account_status','active')->where('approval_status','approved');
  if($available)$q->where('online',1)->where('last_seen_at','>',now()->subMinutes(30));
  return $q->orderBy('name')->get()->filter(function($u)use($p,$kind,$available){$address=$kind==='pickup'?Municipalities::address(DB::table('orders')->where('id',$p->order_id)->value('seller_id')):self::address($p);return Municipalities::matches(Municipalities::address($u->id),$address)&&(!$available||DB::table('assignments')->where('rider_id',$u->id)->whereIn('status',['offered','accepted'])->count()<3);})->values();
 }
 public static function sort(int $id,int $actor,?int $rider=null):void {
  DB::transaction(function()use($id,$actor,$rider){$p=DB::table('parcels')->where('id',$id)->lockForUpdate()->first();abort_unless($p,404);
   abort_unless(DB::table('sorting_centers')->where('id',$p->center_id)->where('user_id',$actor)->exists(),403);
   $areas=self::areas($p);if($rider)$areas=$areas->where('rider_id',$rider);
   $area=$areas->first();if(!$area)Support::fail('area','No area matches the buyer province, municipality and barangay. Create the destination area first, or transfer to the destination center.');
   if($p->status==='AT_SORTING_CENTER')Workflow::move($id,'SORTED',$actor,null,$area->id,$p->center_id);
   elseif(in_array($p->status,['SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER']))DB::table('parcels')->where('id',$id)->update(['delivery_area_id'=>$area->id,'updated_at'=>now()]);
   else Support::fail('parcel','Receive this parcel at the center before starting delivery.');
  });
 }
}
