<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ReturnRouting {
 public static function request($p) { return empty($p->return_request_id)?null:DB::table('return_requests')->find($p->return_request_id); }
 public static function buyer($o) { return $o->address_snapshot?json_decode($o->address_snapshot):DB::table('addresses')->find($o->address_id); }
 public static function pickup($p) { $x=self::request($p);return $x?json_decode($x->pickup_snapshot):Municipalities::address(DB::table('orders')->where('id',$p->order_id)->value('seller_id')); }
 public static function destination($p) { $x=self::request($p);return $x?json_decode($x->destination_snapshot):self::buyer(DB::table('orders')->find($p->order_id)); }
 public static function start($x): void {
  $o=DB::table('orders')->where('id',$x->order_id)->lockForUpdate()->first();
  $p=DB::table('parcels')->where('order_id',$o->id)->lockForUpdate()->first();
  if(!$p||$o->status!=='DELIVERED'||$p->return_request_id)Support::fail('return','Only a delivered order can enter return pickup.');
  $origin=self::buyer($o);$destination=Municipalities::address($o->seller_id);$center=Municipalities::centerFor($origin);
  if(!$origin||!$destination||!$center||!Municipalities::centerFor($destination))Support::fail('return','Both buyer and seller need addresses served by approved sorting centers before approval.');
  DB::table('return_requests')->where('id',$x->id)->update(['pickup_snapshot'=>json_encode($origin),'destination_snapshot'=>json_encode($destination)]);
  DB::table('parcels')->where('id',$p->id)->update(['return_request_id'=>$x->id,'status'=>'PREPARING','center_id'=>$center->id,'destination_center_id'=>null,'delivery_area_id'=>null,'attempts'=>0,'failure_reason'=>null,'updated_at'=>now()]);
  DB::table('orders')->where('id',$o->id)->update(['status'=>'PREPARING','updated_at'=>now()]);
  DB::table('assignments')->insert(['handling_center_id'=>$center->id,'parcel_id'=>$p->id,'rider_id'=>null,'kind'=>'pickup','status'=>'requested','expires_at'=>now()->addHours(12),'earnings'=>0,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('status_history')->insert(['parcel_id'=>$p->id,'from_status'=>$p->status,'to_status'=>'PREPARING','actor_id'=>(int)(auth()->id()??$o->seller_id),'reason'=>'Return approved: collect from buyer and route back to seller.','created_at'=>now(),'updated_at'=>now()]);
  Support::notify([$center->user_id,$o->buyer_id],"Return pickup requested for {$p->tracking_code}. Collect from the buyer; deliver back to the seller.");
 }
}
