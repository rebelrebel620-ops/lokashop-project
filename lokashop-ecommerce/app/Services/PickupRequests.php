<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class PickupRequests {
 public static function deadline(object $a):Carbon{return $a->expires_at?Carbon::parse($a->expires_at):Carbon::parse($a->created_at)->addHours(3);}
 public static function refresh(?int $parcelId=null,?int $sellerId=null):void {
  $q=DB::table('assignments as a')->join('parcels as p','p.id','=','a.parcel_id')->join('orders as o','o.id','=','p.order_id')->where('a.kind','pickup')->where('a.status','offered')->select('a.*');
  if($parcelId!==null)$q->where('p.id',$parcelId);if($sellerId!==null)$q->where('o.seller_id',$sellerId);
  foreach($q->get() as $candidate){DB::transaction(function()use($candidate){
   $p=DB::table('parcels')->where('id',$candidate->parcel_id)->lockForUpdate()->first();$a=DB::table('assignments')->where('id',$candidate->id)->lockForUpdate()->first();
   if(!$a||$a->status!=='offered'||now()->lt(self::deadline($a)))return;
   DB::table('assignments')->where('id',$a->id)->update(['status'=>'expired','updated_at'=>now()]);
   $company=DB::table('sorting_centers')->where('id',$p->center_id)->value('user_id');
   Support::notify([$company],"Pickup {$p->tracking_code} was not accepted within 3 hours. Assign another active rider from the seller area.");
  });}
 }
 public static function request(int $orderId,int $sellerId,?int $ignoredRider=null):void {
  $o=DB::table('orders')->where('id',$orderId)->where('seller_id',$sellerId)->first();abort_unless($o,403);
  $existing=DB::table('parcels')->where('order_id',$orderId)->first();abort_unless($existing,404);self::refresh($existing->id);
  DB::transaction(function()use($existing,$sellerId){
   $p=DB::table('parcels')->where('id',$existing->id)->lockForUpdate()->first();abort_unless(in_array($p->status,['PREPARING','READY_FOR_PICKUP']),422);
   if(DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->whereIn('status',['requested','offered','accepted'])->exists())Support::fail('pickup','A pickup request is already waiting for the center or rider.');
   $center=Municipalities::centerFor(Municipalities::address($sellerId));if(!$center)Support::fail('pickup','No approved sorting center serves your municipality.');
   DB::table('parcels')->where('id',$p->id)->update(['center_id'=>$center->id,'updated_at'=>now()]);
   DB::table('assignments')->insert(['parcel_id'=>$p->id,'rider_id'=>null,'kind'=>'pickup','status'=>'requested','expires_at'=>null,'earnings'=>0,'created_at'=>now(),'updated_at'=>now()]);
   Support::notify([$center->user_id],"Seller requested pickup for {$p->tracking_code}. Assign an active rider from the seller area.");
  });
 }
 public static function offer(int $assignmentId,int $company,int $rider):void {
  $candidate=DB::table('assignments')->find($assignmentId);if($candidate)self::refresh($candidate->parcel_id);
  DB::transaction(function()use($assignmentId,$company,$rider){
   $a=DB::table('assignments')->where('id',$assignmentId)->where('kind','pickup')->first();abort_unless($a,404);
   $p=DB::table('parcels')->where('id',$a->parcel_id)->lockForUpdate()->first();
   abort_unless(DB::table('sorting_centers')->where('id',$p->center_id)->where('user_id',$company)->exists(),403);
   abort_unless(in_array($p->status,['PREPARING','READY_FOR_PICKUP']),422);
   if(DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->whereIn('status',['offered','accepted'])->exists())Support::fail('pickup','A rider already has a live pickup assignment.');
   $latest=DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->orderByDesc('id')->first();abort_unless($latest->id===$a->id&&in_array($a->status,['requested','expired','declined']),422);
   if($a->status!=='requested'&&$a->rider_id==$rider)Support::fail('rider_id','Choose another area rider after a decline or expiry.');
   $u=DB::table('users')->where('id',$rider)->lockForUpdate()->first();
   if(!AreaRouting::riders($p,'pickup')->contains('id',$rider))Support::fail('rider_id','Choose an Online area rider with capacity in the seller barangay.');
   Municipalities::courier($u,$p,'pickup');
   $data=['rider_id'=>$rider,'status'=>'offered','expires_at'=>now()->addHours(3),'earnings'=>0,'updated_at'=>now()];
   if($a->status==='requested')DB::table('assignments')->where('id',$a->id)->update($data);
   else DB::table('assignments')->insert($data+['parcel_id'=>$p->id,'kind'=>'pickup','created_at'=>now()]);
   if($p->status==='PREPARING')Workflow::move($p->id,'READY_FOR_PICKUP',$company,null,null,$p->center_id);
   Support::notify([$rider],"New pickup assignment: {$p->tracking_code}. Accept within 3 hours. It is already visible in Assignments.");
  });
 }
}
