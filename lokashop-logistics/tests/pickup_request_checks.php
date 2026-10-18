<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Auth};
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Services\{PickupRequests,Cart,Commerce,Workflow,Dispatch,Maintenance};
$before=$n;loginAs($buyer);Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));$testOrder=$ids[0];$parcel=DB::table('parcels')->where('order_id',$testOrder)->first();
loginAs($seller);Workflow::move($parcel->id,'CONFIRMED',$seller);Workflow::move($parcel->id,'PREPARING',$seller);
PickupRequests::request($testOrder,$seller,$rider);$attempt=DB::table('assignments')->where('parcel_id',$parcel->id)->first();$original=now()->copy();
ok(Carbon::parse($attempt->expires_at)->equalTo($original->copy()->addHours(12)),'Seller request gets exactly twelve hours');
try{PickupRequests::request($testOrder,$seller,$rider);throw new RuntimeException('Duplicate live pickup accepted');}catch(ValidationException $ex){ok(isset($ex->errors()['pickup']),'Duplicate live pickup is blocked');}
DB::table('assignments')->where('id',$attempt->id)->update(['expires_at'=>$original->copy()->addSeconds(60)]);
if(config('app.site_kind')==='logistics') {
 loginAs($company);Carbon::setTestNow($original->copy()->addHours(2));(new App\Http\Controllers\LogisticsController())->verifyPickup($attempt->id);
 ok(Carbon::parse(DB::table('assignments')->find($attempt->id)->expires_at)->equalTo($original->copy()->addHours(12)),'Center verification does not restart twelve-hour window');
} else {
 DB::table('parcels')->where('id',$parcel->id)->update(['center_id'=>$center]);DB::table('assignments')->where('id',$attempt->id)->update(['status'=>'offered']);Carbon::setTestNow($original->copy()->addHours(2));
}
DB::table('users')->where('id',$rider)->update(['online'=>1,'last_seen_at'=>now()]);loginAs($rider);Dispatch::accept($attempt->id,$rider);ok(DB::table('assignments')->find($attempt->id)->status==='accepted','Rider can accept pickup after two hours, not only sixty seconds');
loginAs($seller);Workflow::move($parcel->id,'READY_FOR_PICKUP',$seller);
Carbon::setTestNow($original->copy()->addHours(12)->subSecond());PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($attempt->id)->status==='accepted','Pickup remains active until full twelve hours elapse');
$notices=DB::table('notifications')->where('user_id',$seller)->where('body','like','%Pickup unsuccessful%')->count();Carbon::setTestNow($original->copy()->addHours(12));Maintenance::run();ok(DB::table('assignments')->find($attempt->id)->status==='expired','Accepted but uncollected pickup expires at twelve hours');
ok(DB::table('notifications')->where('user_id',$seller)->where('body','like','%Pickup unsuccessful%')->count()===$notices+1,'Seller gets unsuccessful pickup notification');
Maintenance::run();ok(DB::table('notifications')->where('user_id',$seller)->where('body','like','%Pickup unsuccessful%')->count()===$notices+1,'Repeated maintenance does not duplicate expiry notification');
ok(DB::table('assignments')->where('parcel_id',$parcel->id)->count()===1,'Maintenance does not silently reassign expired seller pickup');
if(config('app.site_kind')==='ecommerce') {loginAs($seller);$html=(new App\Http\Controllers\SellerController())->orders()->render();ok(str_contains($html,'Request another rider'),'Ready-for-pickup seller can see request-again button');}
$another=user('rider','retrypickuprider');DB::table('users')->where('id',$another)->update(['company_id'=>$company,'courier_type'=>'rider','online'=>1,'last_seen_at'=>now()]);DB::table('addresses')->insert(['user_id'=>$another,'recipient'=>'Retry rider','phone'=>'09171230000','street'=>'Street','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);
loginAs($seller);PickupRequests::request($testOrder,$seller,$another);$retry=DB::table('assignments')->where('parcel_id',$parcel->id)->orderByDesc('id')->first();
ok($retry->rider_id===$another && $retry->status==='requested','Seller can request a different rider after expiry');ok(DB::table('parcels')->find($parcel->id)->center_id===null,'Retry clears old center verification so new request can be verified');
ok(Carbon::parse($retry->expires_at)->equalTo(now()->copy()->addHours(12)),'Retry receives its own new twelve-hour window');
DB::table('parcels')->where('id',$parcel->id)->update(['center_id'=>$center]);DB::table('assignments')->where('id',$retry->id)->update(['status'=>'offered']);loginAs($another);Dispatch::accept($retry->id,$another);requestFor();Workflow::move($parcel->id,'PICKED_UP',$another);Carbon::setTestNow(now()->addHours(13));PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($retry->id)->status==='completed','Successfully collected pickup is never expired later');
// Pending requests expire too, even if the center never verifies them.
DB::table('parcels')->where('id',$parcel->id)->update(['status'=>'PREPARING','center_id'=>null]);DB::table('orders')->where('id',$testOrder)->update(['status'=>'PREPARING']);PickupRequests::request($testOrder,$seller,$rider);$pending=DB::table('assignments')->where('parcel_id',$parcel->id)->orderByDesc('id')->first();Carbon::setTestNow(now()->addHours(12));PickupRequests::refresh($parcel->id);ok(DB::table('assignments')->find($pending->id)->status==='expired','Unverified seller pickup requests also expire');
if(config('app.site_kind')==='logistics') {
 loginAs($rider);requestFor();$controller=new App\Http\Controllers\LogisticsController();$html=$controller->assignments()->render();ok(!str_contains($html,'Work availability'),'Availability buttons are absent from Assignments');
 $r=Illuminate\Http\Request::create('/ops/availability','GET');$r->setLaravelSession($app['session']->driver());$app->instance('request',$r);$html=(new App\Http\Controllers\OperationsController())->panel($r,'availability')->render();ok(str_contains($html,'Work availability')&&str_contains($html,'action="/ops/availability"'),'Availability page contains the work-status controls');
 $r=Illuminate\Http\Request::create('/ops/cod','GET');$r->setLaravelSession($app['session']->driver());$app->instance('request',$r);$html=(new App\Http\Controllers\OperationsController())->panel($r,'cod')->render();ok(!str_contains($html,'Work availability'),'Availability buttons are absent from COD');
}
echo 'PASS: '.($n-$before)." twelve-hour pickup / retry checks.\n";
