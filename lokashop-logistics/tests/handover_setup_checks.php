<?php
require __DIR__.'/bulk_trip_checks.php';
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
use App\Services\BulkTrips;
use App\Http\Controllers\{DriverTripController,CourierSetupController,LogisticsController};
$before=$n;
$t=DB::table('driver_trips')->find($trip2);$pendingStops=DB::table('driver_trip_stops')->where('trip_id',$t->id)->orderBy('position')->get();$s=$pendingStops[0];$own=DB::table('sorting_centers')->find($s->center_id)->user_id;$ownParcels=DB::table('driver_trip_items')->where('stop_id',$s->id)->pluck('parcel_id')->all();$ctrl=new DriverTripController();
loginAs($own);bulkFail(fn()=>$ctrl->receive(requestFor(['parcel_ids'=>$ownParcels]),$s->id),'confirm_receipt','Receipt endpoint requires explicit acknowledgement');
bulkFail(fn()=>$ctrl->receive(requestFor(['confirm_receipt'=>1,'parcel_ids'=>$ownParcels]),$s->id),'handover','Center cannot receive before driver handover');
loginAs($otherDriver);deniedBulk(fn()=>BulkTrips::handover($s->id,$otherDriver),'Unassigned driver cannot confirm handover');
loginAs($driver);bulkFail(fn()=>$ctrl->handover(requestFor(),$s->id),'confirm_handover','Driver handover endpoint requires acknowledgement');
$ctrl->handover(requestFor(['confirm_handover'=>1]),$s->id);$ctrl->handover(requestFor(['confirm_handover'=>1]),$s->id);
ok(DB::table('driver_trip_stops')->find($s->id)->handed_over_by===$driver,'Handover records the assigned driver');
ok(DB::table('driver_trip_items')->where('trip_id',$t->id)->whereNotNull('received_at')->count()===0,'Driver handover never confirms center receipt');
ok(DB::table('driver_trips')->find($t->id)->status==='in_transit','Driver handover does not complete trip');
BulkTrips::refresh();ok(DB::table('driver_trip_items')->where('trip_id',$t->id)->whereNotNull('received_at')->count()===0,'Maintenance does not auto-receive a handed-over parcel');
deniedBulk(fn()=>$ctrl->receive(requestFor(['confirm_receipt'=>1,'parcel_ids'=>$ownParcels]),$s->id),'Driver cannot use center receipt endpoint');
loginAs($owners[1]);deniedBulk(fn()=>BulkTrips::receive($s->id,$owners[1],$ownParcels),'Another destination cannot confirm this stop');
loginAs($own);$ctrl->receive(requestFor(['confirm_receipt'=>1,'parcel_ids'=>[$ownParcels[0]]]),$s->id);
ok(DB::table('driver_trip_items')->where('stop_id',$s->id)->where('parcel_id',$ownParcels[0])->value('received_by')===$own,'Parcel receipt records actual center account');
ok(DB::table('driver_trip_stops')->find($s->id)->received_at===null,'Partial receipt does not complete destination');
$ctrl->receive(requestFor(['confirm_receipt'=>1,'parcel_ids'=>$ownParcels]),$s->id);ok(DB::table('driver_trip_stops')->find($s->id)->received_by===$own,'Completed stop records receiving account');
loginAs($driver);foreach($pendingStops->slice(1) as $pending)BulkTrips::handover($pending->id,$driver);
ok(DB::table('driver_trips')->find($t->id)->status==='in_transit','All driver confirmations still require center confirmations');
foreach($pendingStops->slice(1) as $pending){$receiver=DB::table('sorting_centers')->find($pending->center_id)->user_id;loginAs($receiver);$ids=DB::table('driver_trip_items')->where('stop_id',$pending->id)->pluck('parcel_id')->all();$ctrl->receive(requestFor(['confirm_receipt'=>1,'parcel_ids'=>$ids]),$pending->id);}
ok(DB::table('driver_trips')->find($t->id)->status==='completed','Trip completes only after all explicit center confirmations');
$originalStopTime=DB::table('driver_trip_stops')->find($s->id)->received_at;$originalTripTime=DB::table('driver_trips')->find($t->id)->completed_at;\Illuminate\Support\Carbon::setTestNow(now()->addMinutes(2));loginAs($own);$ctrl->receive(requestFor(['confirm_receipt'=>1,'parcel_ids'=>$ownParcels]),$s->id);ok(DB::table('driver_trip_stops')->find($s->id)->received_at===$originalStopTime&&DB::table('driver_trips')->find($t->id)->completed_at===$originalTripTime,'Receipt replay preserves original receipt and completion timestamps');
ok(DB::table('audit_logs')->where('action','driver-handover')->where('entity_id',$s->id)->count()===1,'Handover retry has one audit event');
loginAs($company);$team=new CourierSetupController();$html=$team->team(requestFor(['type'=>'driver']))->render();ok(str_contains($html,'bulkdriver@example.test')&&!str_contains($html,'rider@example.test'),'Center driver list separates bulk drivers from local riders');
$html=$team->team(requestFor(['type'=>'rider']))->render();ok(str_contains($html,'rider@example.test')&&!str_contains($html,'bulkdriver@example.test'),'Center rider list excludes drivers');
$html=(new LogisticsController())->areas()->render();ok(str_contains($html,'Local rider — pickup and delivery')&&!str_contains($html,'bulkdriver'),'Area selector is for local riders only');
bulkFail(fn()=>(new LogisticsController())->area(requestFor(['name'=>'Bad area','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','rider_id'=>$driver])),'rider_id','Backend rejects driver assigned to a local area');
$admin=user('logistics_admin','handoveradmin');loginAs($admin);requestFor();$links=(new App\Http\Controllers\CommonController())->links();ok(!isset($links['Legacy riders']),'Legacy admin shortcut is hidden');$html=(new App\Http\Controllers\LogisticsWorkspaceController())->overview(requestFor())->render();ok(!str_contains($html,'href="/ops/legacy"'),'Legacy shortcut absent from admin dashboard');ok(strlen((new App\Http\Controllers\LogisticsWorkspaceController())->legacy(requestFor())->render())>100,'Legacy maintenance function remains available');
foreach([$rider,$driver,$company,$admin] as $uid){DB::table('users')->where('id',$uid)->update(['profile_photo'=>'profiles/header-test.png']);loginAs($uid);requestFor();$html=view('dashboard',['links'=>[]])->render();ok(str_contains($html,'/media/profiles/header-test.png'),'Header displays saved photo for account '.$uid);}
loginAs($driver);requestFor();$links=(new App\Http\Controllers\CommonController())->links();ok(isset($links['Trips'])&&!isset($links['Legacy jobs']),'Driver has one main Trips menu');$html=$team->settings(requestFor(),'vehicle')->render();ok(str_contains($html,'Vehicle and documents')&&str_contains($html,'4-wheel truck')&&!str_contains($html,'value="motorcycle"'),'Driver setup shows truck choices only');
loginAs($rider);$html=$team->settings(requestFor(),'vehicle')->render();ok(str_contains($html,'value="motorcycle"')&&!str_contains($html,'value="4-wheel truck"'),'Rider setup shows local vehicles only');$html=$team->settings(requestFor(),'availability')->render();ok(substr_count($html,'<strong>Work availability</strong>')===1&&str_contains($html,'Open Assignments'),'Availability screen has one clear work-status control');
// Optional local HTML previews for visual checks, never customer data.
$preview=getenv('COURIER_PREVIEW_DIR');if($preview){foreach([[$driver,'vehicle','driver'],[$rider,'availability','rider']] as [$uid,$module,$name]){loginAs($uid);requestFor();file_put_contents($preview.'/'.$name.'.html',$team->settings(requestFor(),$module)->render());}loginAs($company);requestFor();file_put_contents($preview.'/team.html',$team->team(requestFor(['type'=>'driver']))->render());}
echo 'PASS: '.($n-$before)." handover, receipt, avatar and setup checks.\n";
