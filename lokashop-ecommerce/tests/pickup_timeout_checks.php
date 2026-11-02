<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Services\{Cart,Commerce,Workflow,PickupRequests,Dispatch,Maintenance};
$before=$n;
function timeoutFixture(?int $sellerId=null):array {
 global $buyer,$seller,$cat,$address;
 $sellerId=$sellerId??$seller;loginAs($buyer);DB::table('cart_items')->where('buyer_id',$buyer)->delete();
 $id=DB::table('products')->insertGetId(['seller_id'=>$sellerId,'category_id'=>$cat,'name'=>'Pickup timeout fixture','price'=>100,'stock'=>20,'approved'=>1,'active'=>1,'weight'=>1]);
 Cart::add(requestFor(['product_id'=>$id,'quantity'=>1]));$orders=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));
 $oid=$orders[0];$p=DB::table('parcels')->where('order_id',$oid)->first();loginAs($sellerId);Workflow::move($p->id,'CONFIRMED',$sellerId);Workflow::move($p->id,'PREPARING',$sellerId);PickupRequests::request($oid,$sellerId);
 return [$p,DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->orderByDesc('id')->first()];
}
function failedPickupNotices($parcel,int $sellerId):int{return DB::table('notifications')->where('user_id',$sellerId)->where('body','like','Pickup unsuccessful for '.$parcel->tracking_code.':%')->count();}
function timeoutInvalid($fn,string $field,string $label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(ValidationException $e){ok(isset($e->errors()[$field]),$label);}}
[$parcel,$assignment]=timeoutFixture();$started=now()->copy();
ok(Carbon::parse($assignment->expires_at)->equalTo($started->copy()->addHours(12)),'New seller request has a 12-hour deadline');
Carbon::setTestNow($started->copy()->addHours(12)->subSecond());PickupRequests::refresh($parcel->id);
ok(DB::table('assignments')->find($assignment->id)->status==='requested','Request remains active one second before deadline');ok(failedPickupNotices($parcel,$seller)===0,'No early seller expiry notification');
Carbon::setTestNow($started->copy()->addHours(12));PickupRequests::refresh($parcel->id);
ok(DB::table('assignments')->find($assignment->id)->status==='expired','Request expires exactly at 12 hours');ok(failedPickupNotices($parcel,$seller)===1,'Seller gets one unsuccessful-pickup notification');
ok(DB::table('parcels')->find($parcel->id)->status==='PREPARING','Expiry preserves parcel status');ok(DB::table('orders')->where('id',$parcel->order_id)->value('status')==='PREPARING','Expiry preserves order status');
PickupRequests::refresh($parcel->id);Maintenance::run();ok(failedPickupNotices($parcel,$seller)===1,'Repeated refresh and maintenance do not duplicate notification');
loginAs($company);timeoutInvalid(fn()=>PickupRequests::offer($assignment->id,$company,$rider),'pickup','Expired unassigned seller request cannot be revived by assigning a rider');
loginAs($seller);PickupRequests::request($parcel->order_id,$seller);$renewed=DB::table('assignments')->where('parcel_id',$parcel->id)->orderByDesc('id')->first();
ok($renewed->id!==$assignment->id&&$renewed->status==='requested','Seller can create a fresh request after expiry');ok(Carbon::parse($renewed->expires_at)->equalTo(now()->addHours(12)),'Fresh request receives its own 12-hour window');
loginAs($company);timeoutInvalid(fn()=>PickupRequests::offer($assignment->id,$company,$rider),'pickup','Stale expired request ID cannot hijack the renewed request');
Carbon::setTestNow(Carbon::parse($renewed->created_at)->addHours(12)->subMinute());DB::table('users')->where('id',$rider)->update(['online'=>1,'last_seen_at'=>now()]);
PickupRequests::offer($renewed->id,$company,$rider);$offered=DB::table('assignments')->find($renewed->id);ok(Carbon::parse($offered->expires_at)->equalTo(now()->addHours(3)),'Assignment starts a separate three-hour rider window');
Carbon::setTestNow(now()->addMinutes(2));PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($renewed->id)->status==='offered','Assigned request survives the original seller deadline');
DB::table('users')->where('id',$rider)->update(['online'=>1,'last_seen_at'=>now()]);loginAs($rider);Dispatch::accept($renewed->id,$rider);Carbon::setTestNow(now()->addHours(15));PickupRequests::refresh($parcel->id);
ok(DB::table('assignments')->find($renewed->id)->status==='accepted','Accepted pickup is not expired by either unaccepted deadline');ok(failedPickupNotices($parcel,$seller)===1,'Accepted pickup produces no extra unsuccessful notice');
// Scope and compatibility: legacy null deadlines and another seller's requests.
[$legacy,$legacyAssignment]=timeoutFixture();DB::table('assignments')->where('id',$legacyAssignment->id)->update(['expires_at'=>null,'created_at'=>now()->subHours(13)]);
$secondSeller=user('seller','timeout-other-seller');DB::table('addresses')->insert(['user_id'=>$secondSeller,'recipient'=>'Test seller','phone'=>'09170000004','street'=>'Origin','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);
[$foreign,$foreignAssignment]=timeoutFixture($secondSeller);DB::table('assignments')->where('id',$foreignAssignment->id)->update(['expires_at'=>now()->subHour(),'created_at'=>now()->subHours(13)]);
PickupRequests::refresh($legacy->id);ok(DB::table('assignments')->find($legacyAssignment->id)->status==='expired','Legacy null-expiry requests use created_at plus 12 hours');ok(DB::table('assignments')->find($foreignAssignment->id)->status==='requested','Parcel-scoped refresh leaves another parcel unchanged');
PickupRequests::refresh(null,$seller);ok(DB::table('assignments')->find($foreignAssignment->id)->status==='requested','Seller-scoped refresh leaves another seller unchanged');
PickupRequests::refresh(null,$secondSeller);ok(DB::table('assignments')->find($foreignAssignment->id)->status==='expired','Matching seller refresh expires the correct request');ok(failedPickupNotices($foreign,$secondSeller)===1,'Matching seller receives one notification');
[$cancelled,$cancelledAssignment]=timeoutFixture();DB::table('assignments')->where('id',$cancelledAssignment->id)->update(['expires_at'=>now()->subHour()]);DB::table('parcels')->where('id',$cancelled->id)->update(['status'=>'CANCELLED']);PickupRequests::refresh($cancelled->id);ok(failedPickupNotices($cancelled,$seller)===0,'Cancelled parcels do not receive misleading pickup-expiry notifications');
echo 'PASS: '.($n-$before)." pickup request timeout checks.\n";
