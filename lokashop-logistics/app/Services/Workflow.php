<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Hash};
class Workflow {
 public static function move(int $parcelId,string $to,int $actorId,?string $reason=null,?int $areaId=null,?int $centerId=null,bool $system=false):void {
  $error=DB::transaction(function()use($parcelId,$to,$actorId,$reason,$areaId,$centerId,$system){
   $p=DB::table('parcels')->where('id',$parcelId)->lockForUpdate()->first();abort_unless($p,404);
   $o=DB::table('orders')->where('id',$p->order_id)->lockForUpdate()->first();$actor=DB::table('users')->find($actorId);abort_unless($actor&&(($system&&$to==='COMPLETED')||($actor->active&&$actor->approval_status==='approved')),403);
   $map=['PLACED'=>['CONFIRMED'=>'seller'],'CONFIRMED'=>['PREPARING'=>'seller'],'PREPARING'=>['READY_FOR_PICKUP'=>'seller'],'READY_FOR_PICKUP'=>['PICKED_UP'=>'rider'],'PICKED_UP'=>['AT_SORTING_CENTER'=>'sorting_center'],'AT_SORTING_CENTER'=>['SORTED'=>'sorting_center'],'SORTED'=>['ASSIGNED_TO_RIDER'=>'sorting_center'],'ASSIGNED_TO_RIDER'=>['OUT_FOR_DELIVERY'=>'rider'],'OUT_FOR_DELIVERY'=>['DELIVERED'=>'rider','DELIVERY_FAILED'=>'rider'],'DELIVERED'=>['COMPLETED'=>'buyer'],'DELIVERY_FAILED'=>['ASSIGNED_TO_RIDER'=>'sorting_center','RETURN_TO_SENDER'=>'sorting_center'],'RETURN_TO_SENDER'=>['RETURNED'=>'sorting_center'],'RETURNED'=>['RETURN_RECEIVED'=>'seller']];
   if($p->status===$to){$already=DB::table('status_history')->where('parcel_id',$p->id)->where('to_status',$to)->where('actor_id',$actorId)->exists();abort_unless($already,403);return null;}
   if(($map[$p->status][$to]??null)!==$actor->role)Support::fail('status','This status change is not allowed. Follow the order sequence.');
   if($actor->role==='seller')abort_unless($o->seller_id==$actorId,403);
   if($actor->role==='buyer')abort_unless($o->buyer_id==$actorId,403);
   if($actor->role==='sorting_center')abort_unless(DB::table('sorting_centers')->where('id',$centerId??$p->center_id)->where('user_id',$actorId)->exists()&&(!$p->center_id||$p->center_id==($centerId??$p->center_id)),403);
   $assignment=null;
   if($actor->role==='rider'){
    $kind=$to==='PICKED_UP'?'pickup':'delivery';
    Municipalities::courier($actor,$p,$kind);$assignment=DB::table('assignments')->where('parcel_id',$p->id)->where('rider_id',$actorId)->where('kind',$kind)->where('status','accepted')->lockForUpdate()->first();abort_unless($assignment,403);
    $company=DB::table('sorting_centers')->where('id',$p->center_id)->value('user_id');abort_unless($actor->company_id&&$company==$actor->company_id,403);
   }
   if($to==='CONFIRMED'){$pay=DB::table('payments')->where('order_id',$o->id)->orderByDesc('id')->first();if($pay&&$pay->method!=='cod'&&$pay->status!=='paid')Support::fail('payment','Payment must be confirmed before accepting this order.');}
   if($to==='READY_FOR_PICKUP'&&!DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->whereIn('status',['offered','accepted'])->exists())Support::fail('pickup','Logistics must verify the pickup request first.');
   if($to==='SORTED'&&!DB::table('delivery_areas')->where('id',$areaId)->where('center_id',$p->center_id)->exists())Support::fail('area_id','Choose an area belonging to your center.');
   $extra=[];$orderExtra=[];
   if($to==='OUT_FOR_DELIVERY'){
    $otp=(string)random_int(100000,999999);$extra['delivery_otp_hash']=Hash::make($otp);$extra['otp_attempts']=0;$extra['otp_locked_until']=null;
    Support::notify([$o->buyer_id],"Order #{$o->id}: delivery OTP {$otp}. Give it to the rider only after receiving your parcel.");
   }
   if($to==='DELIVERED'){
    $r=request();$r->validate(['proof'=>['required','file','mimes:jpg,jpeg,png','extensions:jpg,jpeg,png','max:5120'],'otp'=>['required','regex:/^[0-9]{6}$/'],'cod_amount'=>'nullable|numeric|min:0']);
    if($p->otp_locked_until&&now()->lt($p->otp_locked_until))return ['otp','OTP entry is locked for 15 minutes. Try again later.'];
    if(!$p->delivery_otp_hash||!Hash::check((string)$r->otp,$p->delivery_otp_hash)){
     $tries=($p->otp_locked_until&&now()->gte($p->otp_locked_until))?1:$p->otp_attempts+1;
     DB::table('parcels')->where('id',$p->id)->update(['otp_attempts'=>$tries,'otp_locked_until'=>$tries>=5?now()->addMinutes(15):null]);return ['otp','Incorrect delivery OTP.'];
    }
    $pay=DB::table('payments')->where('order_id',$o->id)->orderByDesc('id')->lockForUpdate()->first();
    if($pay&&$pay->method==='cod'){
     if(!$r->filled('cod_amount')||Support::money($r->cod_amount)!==Support::money($pay->amount))Support::fail('cod_amount','Enter the exact COD amount shown on this order.');
     DB::table('cod_collections')->insertOrIgnore(['parcel_id'=>$p->id,'rider_id'=>$actorId,'company_id'=>$actor->company_id,'amount'=>$pay->amount,'created_at'=>now(),'updated_at'=>now()]);
     DB::table('payments')->where('id',$pay->id)->update(['status'=>'paid','updated_at'=>now()]);
    }elseif($pay&&$pay->status!=='paid')Support::fail('payment','Prepaid payment has not been confirmed.');
    $extra['proof_path']=$r->file('proof')->store('delivery-proofs','local');$orderExtra['delivered_at']=now();
    DB::table('assignments')->where('id',$assignment->id)->update(['status'=>'completed','updated_at'=>now()]);
   }
   if($to==='DELIVERY_FAILED'){
    if(!in_array($reason,['Buyer not home','Wrong address','Refused'],true))Support::fail('reason','Choose Buyer not home, Wrong address or Refused.');
    $extra['attempts']=$p->attempts+1;$extra['failure_reason']=$reason;
    DB::table('assignments')->where('id',$assignment->id)->update(['status'=>'failed','updated_at'=>now()]);
    if($reason==='Refused'||$extra['attempts']>=3)$to='RETURN_TO_SENDER';
   }
   if($to==='PICKED_UP')DB::table('assignments')->where('id',$assignment->id)->update(['status'=>'completed','updated_at'=>now()]);
   if($to==='COMPLETED'){
    if(DB::table('return_requests')->where('order_id',$o->id)->whereIn('status',['requested','approved','disputed'])->exists())Support::fail('order','Resolve the active return request first.');
    $orderExtra['completed_at']=now();
   }
   if($to==='RETURN_RECEIVED'){Commerce::restore($o);Commerce::refund($o);}
   DB::table('parcels')->where('id',$p->id)->update($extra+['status'=>$to,'center_id'=>$centerId??$p->center_id,'delivery_area_id'=>$areaId??$p->delivery_area_id,'updated_at'=>now()]);
   DB::table('orders')->where('id',$o->id)->update($orderExtra+['status'=>$to,'updated_at'=>now()]);
   DB::table('status_history')->insert(['parcel_id'=>$p->id,'from_status'=>$p->status,'to_status'=>$to,'actor_id'=>$actorId,'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
   $riders=DB::table('assignments')->where('parcel_id',$p->id)->pluck('rider_id')->all();$company=DB::table('sorting_centers')->where('id',$centerId??$p->center_id)->value('user_id');
   Support::notify(array_merge([$o->buyer_id,$o->seller_id,$company],$riders),"Order #{$o->id} is {$to}.");return null;
  });
  // Throw outside the transaction so incorrect-OTP counters are not rolled back.
  if($error)Support::fail($error[0],$error[1]);
 }
}
