<?php
require __DIR__.'/workflow_checks.php';
use App\Services\{Cart,Commerce,Workflow,PickupRequests,Dispatch,AreaRouting,Municipalities};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
$start=$n;
function cycleInvalid($fn,$field,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(ValidationException $e){ok(isset($e->errors()[$field]),$label);}}
loginAs($buyer);DB::table('cart_items')->where('buyer_id',$buyer)->delete();Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));$cycleOrder=$ids[0];$parcel=DB::table('parcels')->where('order_id',$cycleOrder)->first();
loginAs($seller);Workflow::move($parcel->id,'CONFIRMED',$seller);Workflow::move($parcel->id,'PREPARING',$seller);PickupRequests::request($cycleOrder,$seller);$a=DB::table('assignments')->where('parcel_id',$parcel->id)->first();
Carbon::setTestNow(now()->addHours(4));PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($a->id)->status==='requested','Waiting seller request does not expire before center assignment');
DB::table('users')->where('id',$rider)->update(['online'=>1,'last_seen_at'=>now()]);loginAs($company);PickupRequests::offer($a->id,$company,$rider);$offer=DB::table('assignments')->find($a->id);
ok(Carbon::parse($offer->expires_at)->equalTo(now()->addHours(3)),'Center assignment starts a three-hour acceptance window');
if(config('app.site_kind')==='logistics'){
 loginAs($rider);$html=(new App\Http\Controllers\LogisticsController())->assignments()->render();ok(str_contains($html,$parcel->tracking_code)&&str_contains($html,'Accept assignment'),'Assignment appears without scanning');ok(!str_contains($html,'Confirm pickup from seller'),'Scan-confirm action hidden before acceptance');
}
Carbon::setTestNow(Carbon::parse($offer->expires_at));PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($a->id)->status==='expired','Unaccepted pickup expires at exactly three hours');
loginAs($rider);cycleInvalid(fn()=>Dispatch::accept($a->id,$rider),'assignment','Expired offer cannot be accepted');
if(config('app.site_kind')==='logistics'){$html=(new App\Http\Controllers\LogisticsController())->assignments()->render();ok(!str_contains($html,'id="assignment-'.$a->id.'"'),'Expired job removed from rider assignment list');}
$r2=user('rider','cyclealternaterider');DB::table('users')->where('id',$r2)->update(['company_id'=>$company,'courier_type'=>'rider','online'=>1,'last_seen_at'=>now()]);DB::table('addresses')->insert(['user_id'=>$r2,'recipient'=>'Alternate','phone'=>'09170000001','street'=>'Alternate','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);DB::table('delivery_areas')->insert(['center_id'=>$center,'name'=>'Alternate local rider','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','rider_id'=>$r2]);
loginAs($company);cycleInvalid(fn()=>PickupRequests::offer($a->id,$company,$rider),'rider_id','Retry must choose another rider');PickupRequests::offer($a->id,$company,$r2);$retry=DB::table('assignments')->where('parcel_id',$parcel->id)->orderByDesc('id')->first();ok($retry->id!==$a->id&&$retry->rider_id===$r2,'Retry creates a fresh area rider offer');
loginAs($r2);Dispatch::accept($retry->id,$r2);
if(config('app.site_kind')==='logistics'){
 $riderApi=new App\Http\Controllers\RiderApiController();$jobs=$riderApi->assignments(requestFor())->getData(true)['data'];$own=collect($jobs)->firstWhere('id',$retry->id);ok($own&&collect($own['actions'])->contains('status','PICKED_UP'),'Rider API exposes scan-confirm action only after acceptance');
}
Carbon::setTestNow(now()->addHours(5));PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($retry->id)->status==='accepted','Accepted pickup does not expire while collecting');
requestFor(['scanned_code'=>'WRONG']);cycleInvalid(fn()=>Workflow::move($parcel->id,'PICKED_UP',$r2),'scanned_code','Wrong parcel code rejected');requestFor(['scanned_code'=>$parcel->tracking_code]);Workflow::move($parcel->id,'PICKED_UP',$r2);ok(DB::table('assignments')->find($retry->id)->status==='accepted','Seller scan keeps pickup in progress');
requestFor();if(config('app.site_kind')==='logistics'){
 $jobs=$riderApi->assignments(requestFor())->getData(true)['data'];$own=collect($jobs)->firstWhere('id',$retry->id);ok(collect($own['actions'])->contains('status','AT_SORTING_CENTER'),'Rider API exposes return-to-center completion after scanning');
 $riderApi->status(requestFor(['status'=>'AT_SORTING_CENTER']),$parcel->id);
}else Workflow::move($parcel->id,'AT_SORTING_CENTER',$r2);ok(DB::table('assignments')->find($retry->id)->status==='completed','Return-to-center button completes pickup');ok(DB::table('orders')->find($cycleOrder)->status==='AT_SORTING_CENTER','Buyer order follows returned parcel');ok((float)DB::table('assignments')->find($retry->id)->earnings===0.0,'Pickup remains free');Workflow::move($parcel->id,'AT_SORTING_CENTER',$r2);ok(DB::table('status_history')->where('parcel_id',$parcel->id)->where('to_status','AT_SORTING_CENTER')->count()===1,'Repeated completion is idempotent');
loginAs($company);$areas=AreaRouting::areas(DB::table('parcels')->find($parcel->id));ok($areas->isNotEmpty()&&$areas->every(fn($x)=>$x->barangay==='Commonwealth'),'Automatic matching uses buyer barangay');
DB::table('users')->where('id',$r2)->update(['online'=>1,'last_seen_at'=>now()]);Dispatch::offer($parcel->id,$company,$r2);$fresh=DB::table('parcels')->find($parcel->id);ok($fresh->status==='ASSIGNED_TO_RIDER','Delivery assignment automatically sorts without choosing an area');ok(DB::table('delivery_areas')->find($fresh->delivery_area_id)->rider_id===$r2,'Auto area matches selected eligible delivery rider');
// Unmatched destination must block dispatch, never fall back to a different barangay.
DB::table('parcels')->where('id',$parcel->id)->update(['status'=>'AT_SORTING_CENTER']);DB::table('assignments')->where('parcel_id',$parcel->id)->where('kind','delivery')->update(['status'=>'expired']);$snap=json_decode(DB::table('orders')->find($cycleOrder)->address_snapshot,true);$snap['barangay']='No coverage test';DB::table('orders')->where('id',$cycleOrder)->update(['address_snapshot'=>json_encode($snap)]);cycleInvalid(fn()=>Dispatch::offer($parcel->id,$company,$r2),'area','Missing matching destination blocks delivery');
if(config('app.site_kind')==='logistics'){$html=(new App\Http\Controllers\LogisticsController())->deliveries()->render();ok(str_contains($html,'No matching area')&&!str_contains($html,'name="area_id"'),'Delivery page shows missing coverage without manual area selector');}
echo 'PASS: '.($n-$start)." pickup cycle and automatic delivery checks.\n";
