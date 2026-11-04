<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class BulkTrips {
 public static function workload(int $driver,array $states=['offered','accepted']):int {
  $q=DB::table('assignments')->where('rider_id',$driver)->whereIn('status',$states);
  return (clone $q)->whereNull('trip_id')->count()+(clone $q)->whereNotNull('trip_id')->distinct()->count('trip_id');
 }
 private static function driver(int $id,bool $online=false){
  $u=DB::table('users')->where('id',$id)->lockForUpdate()->first();
  abort_unless($u&&$u->role==='rider'&&$u->courier_type==='driver'&&$u->active&&$u->account_status==='active'&&$u->approval_status==='approved',403);
  if($online&&(!$u->online||!$u->last_seen_at||now()->diffInMinutes($u->last_seen_at,true)>=30))Support::fail('driver','The driver must be Online.');return $u;
 }
 private static function center(int $actor){$u=DB::table('users')->find($actor);abort_unless($u&&$u->role==='sorting_center'&&$u->active&&$u->approval_status==='approved'&&$u->account_status==='active',403);$c=DB::table('sorting_centers')->where('user_id',$actor)->first();abort_unless($c,403);return $c;}
 public static function create(int $actor,int $driver,array $parcelIds):int {
  self::refresh();
  return DB::transaction(function()use($actor,$driver,$parcelIds){
   $origin=self::center($actor);$u=self::driver($driver,true);abort_unless($u->company_id==$actor,403);
   if(self::workload($driver)>=3)Support::fail('driver','This driver already has 3 active or offered jobs/trips.');
   $ids=array_values(array_unique(array_map('intval',$parcelIds)));sort($ids);if(!$ids||count($ids)>100)Support::fail('parcel_ids','Select 1 to 100 parcels.');
   $parcels=DB::table('parcels')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();abort_unless($parcels->count()===count($ids),422);
   $destinations=[];
   foreach($parcels as $p){abort_unless($p->center_id==$origin->id,403);if(!in_array($p->status,['AT_SORTING_CENTER','SORTED']))Support::fail('parcel_ids','Every selected parcel must be received at your center and waiting for transfer.');
    if(DB::table('assignments')->where('parcel_id',$p->id)->whereIn('status',['offered','accepted','requested'])->exists())Support::fail('parcel_ids','A selected parcel already has an active assignment.');
    Municipalities::courier($u,$p,'transfer');$dest=Municipalities::centerFor(AreaRouting::address($p));
    if(!$dest||$dest->id==$origin->id)Support::fail('parcel_ids','Each parcel needs a different approved destination center. Local parcels belong in Deliveries.');$destinations[$p->id]=$dest;
   }
   if(count(array_unique(array_map(fn($c)=>$c->id,$destinations)))>10)Support::fail('parcel_ids','A trip can contain up to 10 destination centers.');
   $trip=DB::table('driver_trips')->insertGetId(['origin_center_id'=>$origin->id,'driver_id'=>$driver,'status'=>'offered','expires_at'=>now()->addHours(3),'created_at'=>now(),'updated_at'=>now()]);$stops=[];
   foreach($parcels as $p){$dest=$destinations[$p->id];if(!isset($stops[$dest->id]))$stops[$dest->id]=DB::table('driver_trip_stops')->insertGetId(['trip_id'=>$trip,'center_id'=>$dest->id,'position'=>count($stops)+1,'created_at'=>now(),'updated_at'=>now()]);
    // Preserve the existing transfer earnings of PHP 50 per parcel. No new customer charge.
    $assignment=DB::table('assignments')->insertGetId(['handling_center_id'=>$origin->id,'trip_id'=>$trip,'parcel_id'=>$p->id,'rider_id'=>$driver,'kind'=>'transfer','status'=>'offered','expires_at'=>null,'earnings'=>50,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('driver_trip_items')->insert(['trip_id'=>$trip,'stop_id'=>$stops[$dest->id],'parcel_id'=>$p->id,'assignment_id'=>$assignment,'created_at'=>now(),'updated_at'=>now()]);
    self::move($p,'TRANSFER_OFFERED',$actor,'Bulk trip #'.$trip.' to '.$dest->name,['destination_center_id'=>$dest->id,'delivery_area_id'=>null]);
   }
   Support::notify([$driver],"Bulk trip #{$trip}: ".count($ids).' parcels, '.count($stops).' destination centers. Accept the whole trip within 3 hours.');return $trip;
  },3);
 }
 public static function accept(int $id,int $driver):void {
  self::refresh($id);
  DB::transaction(function()use($id,$driver){$u=self::driver($driver,true);$t=DB::table('driver_trips')->where('id',$id)->lockForUpdate()->first();abort_unless($t&&$t->driver_id==$driver,403);if($t->status==='accepted')return;
   if($t->status!=='offered'||now()->gte($t->expires_at))Support::fail('trip','This trip offer is no longer available.');
   $owner=DB::table('sorting_centers')->where('id',$t->origin_center_id)->value('user_id');abort_unless($u->company_id==$owner,403);self::center($owner);
   if(self::workload($driver)>3)Support::fail('trip','Resolve other jobs before accepting this trip.');
   if(DB::table('assignments')->where('trip_id',$id)->where('status','offered')->count()!==DB::table('driver_trip_items')->where('trip_id',$id)->count())Support::fail('trip','A parcel assignment changed. Ask your origin center to cancel and recreate this unaccepted trip.');
   DB::table('driver_trips')->where('id',$id)->update(['status'=>'accepted','accepted_at'=>now(),'updated_at'=>now()]);DB::table('assignments')->where('trip_id',$id)->where('status','offered')->update(['status'=>'accepted','updated_at'=>now()]);
   foreach(DB::table('driver_trip_items')->where('trip_id',$id)->get() as $item)ParcelTracking::note($item->parcel_id,$driver,'Driver accepted the bulk sorting center transfer.');
  },3);
 }
 public static function depart(int $id,int $driver):void {
  DB::transaction(function()use($id,$driver){self::driver($driver);$t=DB::table('driver_trips')->where('id',$id)->lockForUpdate()->first();abort_unless($t&&$t->driver_id==$driver,403);if(in_array($t->status,['in_transit','completed']))return;if($t->status!=='accepted')Support::fail('trip','Accept this trip before starting transport.');
   foreach(DB::table('driver_trip_items')->where('trip_id',$id)->orderBy('parcel_id')->get() as $item){abort_unless(DB::table('assignments')->where('id',$item->assignment_id)->where('status','accepted')->exists(),422);$p=DB::table('parcels')->where('id',$item->parcel_id)->lockForUpdate()->first();abort_unless($p->center_id==$t->origin_center_id&&$p->status==='TRANSFER_OFFERED',422);$stop=DB::table('driver_trip_stops')->find($item->stop_id);$dest=DB::table('sorting_centers')->find($stop->center_id);self::center($dest->user_id);self::move($p,'IN_TRANSIT_CENTER',$driver,'Bulk trip #'.$id.' departed origin center.');}
   DB::table('driver_trips')->where('id',$id)->update(['status'=>'in_transit','departed_at'=>now(),'updated_at'=>now()]);
  },3);
 }
 public static function handover(int $stopId,int $driver):void {
  DB::transaction(function()use($stopId,$driver){self::driver($driver);$candidate=DB::table('driver_trip_stops')->find($stopId);abort_unless($candidate,404);
   $trip=DB::table('driver_trips')->where('id',$candidate->trip_id)->lockForUpdate()->first();abort_unless($trip&&$trip->driver_id==$driver,403);
   $stop=DB::table('driver_trip_stops')->where('id',$stopId)->lockForUpdate()->first();if($stop->handed_over_at)return;
   if($trip->status!=='in_transit'||$stop->received_at)Support::fail('handover','Start this trip before confirming handover at a pending stop.');
   DB::table('driver_trip_stops')->where('id',$stopId)->update(['handed_over_at'=>now(),'handed_over_by'=>$driver,'updated_at'=>now()]);
   DB::table('assignments')->whereIn('id',DB::table('driver_trip_items')->where('stop_id',$stopId)->select('assignment_id'))->update(['handed_over_at'=>now(),'handed_over_by'=>$driver,'updated_at'=>now()]);
   Support::audit('driver-handover','driver_trip_stop',$stopId,null,['trip_id'=>$trip->id,'driver_id'=>$driver]);
   $dest=DB::table('sorting_centers')->find($stop->center_id);Support::notify([$dest->user_id],"Driver confirmed handover for trip #{$trip->id}. Check the parcels, then confirm receipt at your center.");
  },3);
 }
 public static function receive(int $stopId,int $actor,array $parcelIds):void {
  DB::transaction(function()use($stopId,$actor,$parcelIds){$center=self::center($actor);$candidate=DB::table('driver_trip_stops')->find($stopId);abort_unless($candidate&&$candidate->center_id==$center->id,403);
   $trip=DB::table('driver_trips')->where('id',$candidate->trip_id)->lockForUpdate()->first();if(!in_array($trip->status,['in_transit','completed']))Support::fail('trip','Wait for the driver to start transport.');
   $stop=DB::table('driver_trip_stops')->where('id',$stopId)->lockForUpdate()->first();$ids=array_values(array_unique(array_map('intval',$parcelIds)));if(!$ids)Support::fail('parcel_ids','Select parcels physically received at your center.');
   if(!$stop->received_at&&(!$stop->handed_over_at||$stop->handed_over_by!=$trip->driver_id))Support::fail('handover','The assigned driver must confirm handover at this stop before the sorting center can confirm receipt.');
   $items=DB::table('driver_trip_items')->where('stop_id',$stopId)->whereIn('parcel_id',$ids)->orderBy('parcel_id')->lockForUpdate()->get();abort_unless($items->count()===count($ids),403);
   $changed=false;foreach($items as $item){if($item->received_at)continue;$changed=true;$p=DB::table('parcels')->where('id',$item->parcel_id)->lockForUpdate()->first();abort_unless($p->status==='IN_TRANSIT_CENTER'&&$p->destination_center_id==$center->id,422);
    self::move($p,'AT_SORTING_CENTER',$actor,'Received at stop of bulk trip #'.$trip->id,['center_id'=>$center->id,'destination_center_id'=>null,'delivery_area_id'=>null]);
    DB::table('driver_trip_items')->where('id',$item->id)->update(['received_at'=>now(),'received_by'=>$actor,'updated_at'=>now()]);DB::table('assignments')->where('id',$item->assignment_id)->where('status','accepted')->update(['status'=>'completed','received_by'=>$actor,'updated_at'=>now()]);
   }
   if(!$stop->received_at&&!DB::table('driver_trip_items')->where('stop_id',$stopId)->whereNull('received_at')->exists())DB::table('driver_trip_stops')->where('id',$stopId)->update(['received_at'=>now(),'received_by'=>$actor,'updated_at'=>now()]);
   if($trip->status!=='completed'&&DB::table('driver_trip_items')->where('trip_id',$trip->id)->exists()&&!DB::table('driver_trip_items')->where('trip_id',$trip->id)->whereNull('received_at')->exists()&&!DB::table('driver_trip_stops')->where('trip_id',$trip->id)->whereNull('received_at')->exists())DB::table('driver_trips')->where('id',$trip->id)->update(['status'=>'completed','completed_at'=>now(),'updated_at'=>now()]);
   if($changed)Support::audit('center-receipt','driver_trip_stop',$stopId,null,['trip_id'=>$trip->id,'receiver_id'=>$actor,'parcel_ids'=>$ids,'driver_id'=>$stop->handed_over_by]);
   if($changed)Support::notify([$trip->driver_id],"Receipt recorded for bulk trip #{$trip->id} at {$center->name}.");
  },3);
 }
 public static function close(int $id,int $actor,string $status):void {
  abort_unless(in_array($status,['declined','cancelled','expired']),422);
  DB::transaction(function()use($id,$actor,$status){$t=DB::table('driver_trips')->where('id',$id)->lockForUpdate()->first();abort_unless($t,404);
   if($status==='declined'){self::driver($actor);abort_unless($t->driver_id==$actor,403);}elseif($status==='cancelled')abort_unless(self::center($actor)->id==$t->origin_center_id,403);
   if($t->status===$status||($status==='expired'&&$t->status!=='offered'))return;if($t->status!=='offered')Support::fail('trip','Only an unaccepted trip can be declined, cancelled or expired.');
   if($status==='expired'&&now()->lt($t->expires_at))return;
   foreach(DB::table('driver_trip_items')->where('trip_id',$id)->orderBy('parcel_id')->get() as $item){$p=DB::table('parcels')->where('id',$item->parcel_id)->lockForUpdate()->first();abort_unless($p->center_id==$t->origin_center_id&&$p->status==='TRANSFER_OFFERED',422);self::move($p,'AT_SORTING_CENTER',$actor?:DB::table('sorting_centers')->where('id',$t->origin_center_id)->value('user_id'),($status==='expired'?'Automatic timeout: ':'').'Bulk trip #'.$id.' '.$status.'; awaiting reassignment.',['destination_center_id'=>null,'delivery_area_id'=>null]);}
   DB::table('assignments')->where('trip_id',$id)->where('status','offered')->update(['status'=>$status,'updated_at'=>now()]);DB::table('driver_trips')->where('id',$id)->update(['status'=>$status,'updated_at'=>now()]);
   Support::notify([DB::table('sorting_centers')->where('id',$t->origin_center_id)->value('user_id')],"Bulk trip #{$id} {$status}. Its parcels can be assigned to another trip.");
  },3);
 }
 public static function refresh(?int $id=null):void {
  $q=DB::table('driver_trips')->where('status','offered')->where('expires_at','<=',now());if($id)$q->where('id',$id);foreach($q->get() as $t)self::close($t->id,0,'expired');
 }
 private static function move($p,string $status,?int $actor,string $reason,array $extra=[]):void {
  DB::table('parcels')->where('id',$p->id)->update($extra+['status'=>$status,'updated_at'=>now()]);DB::table('orders')->where('id',$p->order_id)->update(['status'=>$status,'updated_at'=>now()]);
  DB::table('status_history')->insert(['parcel_id'=>$p->id,'from_status'=>$p->status,'to_status'=>$status,'actor_id'=>$actor,'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);$o=DB::table('orders')->find($p->order_id);Support::notify([$o->buyer_id,$o->seller_id],"Order #{$o->id}: {$status}. {$reason}");
 }
}
