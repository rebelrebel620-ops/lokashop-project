<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\{Cart,Commerce,Workflow,PickupRequests,Dispatch};
use App\Http\Controllers\RiderApiController;
$before=$n;
function scanFixture():array {
 global $buyer,$seller,$company,$rider,$cat,$address;
 loginAs($buyer);DB::table('cart_items')->where('buyer_id',$buyer)->delete();
 $p=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Scan fixture','price'=>100,'stock'=>20,'active'=>1,'approved'=>1,'weight'=>1]);
 Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));
 $parcel=DB::table('parcels')->where('order_id',$ids[0])->first();loginAs($seller);Workflow::move($parcel->id,'CONFIRMED',$seller);Workflow::move($parcel->id,'PREPARING',$seller);PickupRequests::request($ids[0],$seller);
 $a=DB::table('assignments')->where('parcel_id',$parcel->id)->where('kind','pickup')->orderByDesc('id')->first();loginAs($company);DB::table('users')->where('id',$rider)->update(['online'=>1,'last_seen_at'=>now()]);PickupRequests::offer($a->id,$company,$rider);return [$parcel,$a];
}
[$parcel,$a]=scanFixture();loginAs($rider);Dispatch::accept($a->id,$rider);$api=new RiderApiController();
$code='LKS-OKUMKULQZK1H';DB::table('parcels')->where('id',$parcel->id)->update(['tracking_code'=>$code]);
foreach([$code,'https://lokashop.trade/track/'.$code,'https://lokashop.trade/track?code='.$code,strtolower($code),"\xEF\xBB\xBF".$code."\xE2\x80\x8B"] as $label){
 $response=$api->scan(requestFor(['code'=>$label,'assignment_id'=>$a->id]));$data=$response->getData(true);
 ok($response->getStatusCode()===200&&$data['assignment']['id']===$a->id,'Owned accepted pickup can verify this supported QR format');
 ok(collect($data['assignment']['actions'])->contains('status','PICKED_UP'),'Verified ready parcel exposes the pickup action');
}
$response=$api->scan(requestFor(['code'=>$code]));ok($response->getStatusCode()===200,'General scanner still finds owned accepted pickup');
ok(DB::table('parcels')->find($parcel->id)->status==='READY_FOR_PICKUP','Scanning alone never confirms pickup');
ok(DB::table('assignments')->find($a->id)->status==='accepted','Scanning alone does not complete the assignment');
[$otherParcel,$otherAssignment]=scanFixture();loginAs($rider);
$response=$api->scan(requestFor(['code'=>$otherParcel->tracking_code,'assignment_id'=>$a->id]));ok($response->getStatusCode()===422,'Wrong job label cannot unlock the selected assignment');
$response=$api->scan(requestFor(['code'=>$otherParcel->tracking_code,'assignment_id'=>$otherAssignment->id]));ok($response->getStatusCode()===409&&str_contains($response->getData(true)['message'],'Accept'),'Offered assignment asks rider to accept rather than falsely saying unassigned');
$foreign=user('rider','scanforeign');loginAs($foreign);$response=$api->scan(requestFor(['code'=>$code,'assignment_id'=>$a->id]));ok($response->getStatusCode()===403,'Another courier cannot scan by stealing the assignment ID');
loginAs($rider);
try{$api->status(requestFor(['status'=>'PICKED_UP']),$parcel->id);throw new RuntimeException('Missing label accepted');}catch(ValidationException $e){ok(isset($e->errors()['scanned_code']),'Confirmation requires the verified label value');}
try{$api->status(requestFor(['status'=>'PICKED_UP','scanned_code'=>$otherParcel->tracking_code]),$parcel->id);throw new RuntimeException('Wrong label accepted');}catch(ValidationException $e){ok(isset($e->errors()['scanned_code']),'Confirmation re-checks label equality');}
$api->status(requestFor(['status'=>'PICKED_UP','scanned_code'=>$code]),$parcel->id);ok(DB::table('parcels')->find($parcel->id)->status==='PICKED_UP','Correct verified label permits pickup confirmation');ok(DB::table('assignments')->find($a->id)->status==='accepted','Pickup remains ongoing until return to center');
DB::table('assignments')->where('id',$otherAssignment->id)->update(['status'=>'expired']);$response=$api->scan(requestFor(['code'=>$otherParcel->tracking_code,'assignment_id'=>$otherAssignment->id]));ok($response->getStatusCode()===409&&str_contains($response->getData(true)['message'],'expired'),'Expired assignment has a clear refresh message');
echo 'PASS: '.($n-$before)." pickup scan verification checks.\n";
