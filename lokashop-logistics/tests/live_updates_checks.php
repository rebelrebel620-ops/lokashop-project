<?php
require __DIR__.'/workflow_checks.php';
use App\Services\LiveUpdates;
$start=$n;
foreach([$buyer,$seller,$company,$rider,user('admin','liveadmin'),user('logistics_admin','livelogadmin')] as $uid){loginAs($uid);$state=LiveUpdates::snapshot();ok(strlen($state['revision'])===64,'Role snapshot succeeds');ok($state===LiveUpdates::snapshot(),'Unchanged data has stable revision');}
loginAs($buyer);$before=LiveUpdates::snapshot();DB::table('users')->where('id',$buyer)->update(['last_seen_at'=>now()->addMinute()]);ok($before===LiveUpdates::snapshot(),'Polling does not change presence revision');
DB::table('orders')->where('buyer_id',$buyer)->update(['total'=>999]);ok($before!==LiveUpdates::snapshot(),'Buyer order change detected');
$before=LiveUpdates::snapshot();DB::table('products')->where('id',$p)->update(['stock'=>123]);ok($before!==LiveUpdates::snapshot(),'Catalog stock change detected');
Auth::forgetUser();$before=LiveUpdates::snapshot();ok(!$before['authenticated'],'Guest snapshot available');DB::table('products')->where('id',$p)->update(['stock'=>124]);ok($before!==LiveUpdates::snapshot(),'Guest catalog change detected');
echo 'Live updates: '.($n-$start)." checks passed\n";
