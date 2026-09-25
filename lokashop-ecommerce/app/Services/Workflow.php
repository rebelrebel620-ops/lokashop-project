<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class Workflow {
 public static function move(int $parcelId,string $to,int $actorId,?string $reason=null,?int $areaId=null,?int $centerId=null): void {
  DB::transaction(function() use($parcelId,$to,$actorId,$reason,$areaId,$centerId){
   $p=DB::table('parcels')->where('id',$parcelId)->lockForUpdate()->first(); abort_unless($p,404);
   $o=DB::table('orders')->where('id',$p->order_id)->first(); $actor=DB::table('users')->where('id',$actorId)->first();
   $map=['PLACED'=>['CONFIRMED'=>'seller'],'CONFIRMED'=>['PREPARING'=>'seller'],'PREPARING'=>['READY_FOR_PICKUP'=>'seller'],'READY_FOR_PICKUP'=>['PICKED_UP'=>'rider'],'PICKED_UP'=>['AT_SORTING_CENTER'=>'sorting_center'],'AT_SORTING_CENTER'=>['SORTED'=>'sorting_center'],'SORTED'=>['ASSIGNED_TO_RIDER'=>'sorting_center'],'ASSIGNED_TO_RIDER'=>['OUT_FOR_DELIVERY'=>'rider'],'OUT_FOR_DELIVERY'=>['DELIVERED'=>'rider','DELIVERY_FAILED'=>'rider'],'DELIVERED'=>['COMPLETED'=>'buyer'],'DELIVERY_FAILED'=>['ASSIGNED_TO_RIDER'=>'sorting_center','RETURNED'=>'sorting_center']];
   abort_unless(($map[$p->status][$to]??null)===$actor->role,403,'Invalid transition or role');
   if($actor->role==='seller') abort_unless($o->seller_id===$actorId,403);
   if($actor->role==='buyer') abort_unless($o->buyer_id===$actorId,403);
   if($actor->role==='sorting_center') { $owned=DB::table('sorting_centers')->where('id',$centerId??$p->center_id)->where('user_id',$actorId)->exists();abort_unless($owned,403); }
   if($actor->role==='rider') { $kind=$to==='PICKED_UP'||$to==='AT_SORTING_CENTER'?'pickup':'delivery';abort_unless(DB::table('assignments')->where('parcel_id',$parcelId)->where('rider_id',$actorId)->where('kind',$kind)->where('status','accepted')->exists(),403); }
   if($to==='DELIVERY_FAILED' && !trim((string)$reason)) throw ValidationException::withMessages(['reason'=>'Enter a failure reason.']);
   if($to==='SORTED') abort_unless($areaId && DB::table('delivery_areas')->where('id',$areaId)->where('center_id',$centerId??$p->center_id)->exists(),422);
   if($to==='READY_FOR_PICKUP') abort_unless(DB::table('assignments')->where('parcel_id',$parcelId)->where('kind','pickup')->whereIn('status',['offered','accepted'])->exists(),422,'Request a pickup first');
   if($to==='PICKED_UP'||$to==='AT_SORTING_CENTER') DB::table('assignments')->where('parcel_id',$parcelId)->where('kind','pickup')->update(['status'=>'completed','updated_at'=>now()]);
   if($to==='OUT_FOR_DELIVERY'||$to==='DELIVERED'||$to==='DELIVERY_FAILED') abort_unless(DB::table('assignments')->where('parcel_id',$parcelId)->where('kind','delivery')->where('rider_id',$actorId)->where('status','accepted')->exists(),403);
   if($to==='DELIVERED') DB::table('assignments')->where('parcel_id',$parcelId)->where('kind','delivery')->where('rider_id',$actorId)->update(['status'=>'completed','updated_at'=>now()]);
   DB::table('parcels')->where('id',$parcelId)->update(['status'=>$to,'center_id'=>$centerId??$p->center_id,'delivery_area_id'=>$areaId??$p->delivery_area_id,'failure_reason'=>$to==='DELIVERY_FAILED'?$reason:$p->failure_reason,'updated_at'=>now()]);
   DB::table('orders')->where('id',$o->id)->update(['status'=>$to,'updated_at'=>now()]);
   DB::table('status_history')->insert(['parcel_id'=>$parcelId,'from_status'=>$p->status,'to_status'=>$to,'actor_id'=>$actorId,'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('notifications')->insert([['user_id'=>$o->buyer_id,'body'=>'Order #'.$o->id.' is '.$to,'created_at'=>now(),'updated_at'=>now()],['user_id'=>$o->seller_id,'body'=>'Order #'.$o->id.' is '.$to,'created_at'=>now(),'updated_at'=>now()]]);
  });
 }
}
