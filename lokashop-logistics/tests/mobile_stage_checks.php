<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\{RiderApiController,MobileRiderController};
$before=$n;
$local=user('rider','mobile-stage-rider');DB::table('users')->where('id',$local)->update(['company_id'=>$company]);
foreach(['offered','accepted','completed'] as $state)DB::table('assignments')->insert(['parcel_id'=>$pid,'rider_id'=>$local,'kind'=>'delivery','status'=>$state,'expires_at'=>$state==='offered'?now()->addHours(3):null,'earnings'=>0,'created_at'=>now(),'updated_at'=>now()]);
loginAs($local);$controller=new RiderApiController();
foreach(['new'=>['offered'],'progress'=>['accepted'],'completed'=>['completed'],'active'=>['offered','accepted']] as $filter=>$expected){$r=requestFor();$r->query->set('filter',$filter);$rows=$controller->assignments($r)->getData(true)['data'];$actual=collect($rows)->pluck('status')->sort()->values()->all();sort($expected);ok($actual===$expected,'Mobile filter '.$filter.' selects only its intended stage');}
$overview=(new MobileRiderController())->overview(requestFor())->getData(true);
ok(count($overview['current_work'])===1&&$overview['current_work'][0]['tracking_code']===DB::table('parcels')->find($pid)->tracking_code,'Overview returns only own accepted work');
ok(!isset($overview['current_work'][0]['password'],$overview['current_work'][0]['remember_token']),'Overview work contains no account secrets');
$driver=user('rider','mobile-stage-driver');DB::table('users')->where('id',$driver)->update(['company_id'=>$company,'courier_type'=>'driver']);DB::table('driver_trips')->insert(['origin_center_id'=>$center,'driver_id'=>$driver,'status'=>'completed','completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
foreach(['offered','accepted','in_transit'] as $state)DB::table('driver_trips')->insert(['origin_center_id'=>$center,'driver_id'=>$driver,'status'=>$state,'expires_at'=>now()->addHours(3),'created_at'=>now(),'updated_at'=>now()]);
loginAs($driver);$overview=(new MobileRiderController())->overview(requestFor())->getData(true);ok($overview['completed_trips']===1&&$overview['today_completed_trips']===1,'Driver overview counts completed grouped trips independently of parcel assignments');ok($overview['trip_offers']===1&&$overview['active_trips']===2,'Driver counters count grouped trips instead of parcels');ok($overview['current_work']===[],'Driver overview does not expose another courier work');
echo 'PASS: '.($n-$before)." mobile stage and overview checks.\n";
