<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Services\{BulkTrips,Municipalities};
$before=$n;
function bulkFail($fn,$field,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(ValidationException $e){ok(isset($e->errors()[$field]),$label);}}
function deniedBulk($fn,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok(in_array($e->getStatusCode(),[403,404]),$label);}}
$driver=user('rider','bulkdriver');DB::table('users')->where('id',$driver)->update(['company_id'=>$company,'courier_type'=>'driver','vehicle'=>'6-wheel truck','online'=>1,'last_seen_at'=>now()]);
$centers=[];$owners=[];$parcelGroups=[];
foreach(['Los Baños','Bay','Calauan'] as $i=>$city){
 $owner=user('sorting_center','bulkcenter'.$i);$owners[]=$owner;$c=DB::table('sorting_centers')->insertGetId(['user_id'=>$owner,'name'=>'Bulk destination '.$i,'city'=>$city,'province'=>'Laguna','municipality_key'=>Municipalities::key('Laguna',$city)]);$centers[]=$c;
 DB::table('addresses')->insert(['user_id'=>$owner,'recipient'=>'Center','phone'=>'09170000000','street'=>'Center street','barangay'=>'Poblacion','city'=>$city,'province'=>'Laguna','is_default'=>1]);
 $parcelGroups[$c]=[];for($j=0;$j<2;$j++){
  $a=['recipient'=>'Buyer','phone'=>'09171111111','street'=>'Buyer street','barangay'=>'Poblacion','city'=>$city,'province'=>'Laguna'];
  $o=DB::table('orders')->insertGetId(['buyer_id'=>$buyer,'seller_id'=>$seller,'address_id'=>$address,'address_snapshot'=>json_encode($a),'status'=>'AT_SORTING_CENTER','total'=>150,'shipping_fee'=>50,'merchandise_total'=>100,'created_at'=>now(),'updated_at'=>now()]);
  $parcelGroups[$c][]=DB::table('parcels')->insertGetId(['order_id'=>$o,'tracking_code'=>'BULK-'.$i.'-'.$j,'status'=>'AT_SORTING_CENTER','center_id'=>$center,'created_at'=>now(),'updated_at'=>now()]);
 }
}
$all=array_merge(...array_values($parcelGroups));loginAs($company);$trip=BulkTrips::create($company,$driver,$all);
ok(DB::table('driver_trip_stops')->where('trip_id',$trip)->count()===3,'Six parcels group into three destination stops');
ok(DB::table('assignments')->where('trip_id',$trip)->count()===6,'Each parcel retains one assignment ledger');ok(BulkTrips::workload($driver)===1,'Many parcels count as one offered trip');
bulkFail(fn()=>BulkTrips::create($company,$driver,$all),'parcel_ids','Parcels cannot be booked into two active trips');
$otherDriver=user('rider','bulkotherdriver');DB::table('users')->where('id',$otherDriver)->update(['company_id'=>$company,'courier_type'=>'driver','online'=>1,'last_seen_at'=>now()]);
loginAs($otherDriver);deniedBulk(fn()=>BulkTrips::accept($trip,$otherDriver),'Other driver cannot accept trip');
loginAs($driver);BulkTrips::accept($trip,$driver);BulkTrips::accept($trip,$driver);ok(DB::table('assignments')->where('trip_id',$trip)->where('status','accepted')->count()===6,'One acceptance accepts all trip parcels idempotently');
$beforeHistory=DB::table('status_history')->whereIn('parcel_id',$all)->count();BulkTrips::depart($trip,$driver);BulkTrips::depart($trip,$driver);
ok(DB::table('orders')->whereIn('id',DB::table('parcels')->whereIn('id',$all)->pluck('order_id'))->where('status','IN_TRANSIT_CENTER')->count()===6,'All ecommerce orders follow bulk departure');ok(DB::table('status_history')->whereIn('parcel_id',$all)->count()===$beforeHistory+6,'Departure replay does not duplicate parcel histories');
$stops=DB::table('driver_trip_stops')->where('trip_id',$trip)->orderBy('position')->get();
loginAs($driver);foreach($stops as $stop)BulkTrips::handover($stop->id,$driver);
loginAs($owners[1]);deniedBulk(fn()=>BulkTrips::receive($stops[0]->id,$owners[1],$parcelGroups[$centers[0]]),'A center cannot receive another stop');
loginAs($owners[0]);deniedBulk(fn()=>BulkTrips::receive($stops[0]->id,$owners[0],[$parcelGroups[$centers[1]][0]]),'A stop cannot receive foreign parcels');
BulkTrips::receive($stops[0]->id,$owners[0],[$parcelGroups[$centers[0]][0]]);ok(DB::table('driver_trip_stops')->find($stops[0]->id)->received_at===null,'Partial handover leaves stop open');ok(DB::table('driver_trips')->find($trip)->status==='in_transit','Partial handover leaves whole trip open');ok(DB::table('parcels')->find($parcelGroups[$centers[0]][0])->center_id===$centers[0],'Received parcel moves to destination center');
$history=DB::table('status_history')->count();BulkTrips::receive($stops[0]->id,$owners[0],[$parcelGroups[$centers[0]][0]]);ok(DB::table('status_history')->count()===$history,'Receipt replay does not duplicate state/history');
BulkTrips::receive($stops[0]->id,$owners[0],$parcelGroups[$centers[0]]);ok(DB::table('driver_trip_stops')->find($stops[0]->id)->received_at!==null,'Stop finishes after all own parcels arrive');
loginAs($owners[2]);BulkTrips::receive($stops[2]->id,$owners[2],$parcelGroups[$centers[2]]);ok(DB::table('driver_trips')->find($trip)->status==='in_transit','Stops may finish out of order; trip still waits for remaining stop');
loginAs($owners[1]);BulkTrips::receive($stops[1]->id,$owners[1],$parcelGroups[$centers[1]]);ok(DB::table('driver_trips')->find($trip)->status==='completed','Trip finishes after every stop and parcel is received');ok(BulkTrips::workload($driver)===0,'Completed bulk trip releases driver capacity');ok(DB::table('assignments')->where('trip_id',$trip)->where('status','completed')->count()===6,'All six earnings ledger rows complete once');
// Decline, cancel and timeout return ALL parcels to origin queue without new orders.
foreach(['declined','cancelled','expired'] as $reason){
 foreach($all as $pid2){DB::table('parcels')->where('id',$pid2)->update(['center_id'=>$center,'status'=>'AT_SORTING_CENTER','destination_center_id'=>null]);DB::table('orders')->where('id',DB::table('parcels')->find($pid2)->order_id)->update(['status'=>'AT_SORTING_CENTER']);}
 DB::table('users')->where('id',$driver)->update(['last_seen_at'=>now(),'online'=>1]);loginAs($company);$next=BulkTrips::create($company,$driver,$all);
 if($reason==='expired'){Carbon::setTestNow(now()->addHours(3));BulkTrips::refresh($next);}else{loginAs($reason==='declined'?$driver:$company);BulkTrips::close($next,$reason==='declined'?$driver:$company,$reason);}
 ok(DB::table('driver_trips')->find($next)->status===$reason,'Whole trip '.$reason.' state persists');ok(DB::table('parcels')->whereIn('id',$all)->where('status','AT_SORTING_CENTER')->whereNull('destination_center_id')->count()===6,'All parcels return to queue after '.$reason);
}
if(config('app.site_kind')==='logistics'){
 loginAs($company);requestFor();$html=(new App\Http\Controllers\DriverTripController())->history()->render();ok(str_contains($html,'Trip history')&&str_contains($html,'Expired'),'Origin sees closed bulk-trip history');
 $html=(new App\Http\Controllers\TransferController())->index()->render();ok(str_contains($html,'Create one bulk trip')&&str_contains($html,'bulk-trip-form'),'Origin transfer page renders bulk selection');
 DB::table('users')->where('id',$driver)->update(['last_seen_at'=>now(),'online'=>1]);$trip2=BulkTrips::create($company,$driver,$all);loginAs($driver);
 $api=new App\Http\Controllers\DriverTripController();$data=$api->index(requestFor())->getData(true);ok(count($data['data'])===1&&count($data['data'][0]['stops'])===3,'Trip API lists one trip with three stops');
 $req=requestFor();$req->headers->set('Accept','text/html');$html=$api->index($req)->render();ok(str_contains($html,'Accept whole trip'),'Driver web accepts whole trip once');BulkTrips::accept($trip2,$driver);BulkTrips::depart($trip2,$driver);
 loginAs($owners[0]);requestFor();$html=(new App\Http\Controllers\TransferController())->index()->render();ok(str_contains($html,'BULK-0-0')&&!str_contains($html,'BULK-1-0'),'Incoming center view only exposes its own trip parcels');
 loginAs($otherDriver);deniedBulk(fn()=>$api->show(requestFor(),$trip2),'Trip API prevents unrelated driver manifest access');
}
// Capacity counts trips, not the six parcels inside them.
function extraBulkParcel($destination){global $buyer,$seller,$address,$center;
 $c=DB::table('sorting_centers')->find($destination);$o=DB::table('orders')->insertGetId(['buyer_id'=>$buyer,'seller_id'=>$seller,'address_id'=>$address,'address_snapshot'=>json_encode(['city'=>$c->city,'province'=>$c->province,'barangay'=>'Poblacion']),'status'=>'AT_SORTING_CENTER','total'=>100]);return DB::table('parcels')->insertGetId(['order_id'=>$o,'tracking_code'=>'BULK-EXTRA-'.bin2hex(random_bytes(4)),'center_id'=>$center,'status'=>'AT_SORTING_CENTER']);}
loginAs($company);DB::table('users')->where('id',$driver)->update(['online'=>1,'last_seen_at'=>now()]);
$live=BulkTrips::workload($driver);for($i=$live;$i<3;$i++)BulkTrips::create($company,$driver,[extraBulkParcel($centers[0])]);
ok(BulkTrips::workload($driver)===3,'Three grouped trips fit capacity even with many parcels');$fourth=extraBulkParcel($centers[0]);bulkFail(fn()=>BulkTrips::create($company,$driver,[$fourth]),'driver','Fourth active/offered trip is blocked');
if(config('app.site_kind')==='logistics'){
 DB::table('users')->where('id',$otherDriver)->update(['online'=>1,'last_seen_at'=>now()]);$key=(string)Illuminate\Support\Str::uuid();$r=requestFor(['driver_id'=>$otherDriver,'parcel_ids'=>[$fourth],'_request_key'=>$key]);$ctrl=new App\Http\Controllers\DriverTripController();$count=DB::table('driver_trips')->count();$ctrl->create($r);$ctrl->create($r);ok(DB::table('driver_trips')->count()===$count+1,'Double submission creates one trip using the request key');
}
echo 'PASS: '.($n-$before)." bulk trip integration checks.\n";
