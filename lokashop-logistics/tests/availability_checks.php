<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
$before=$n;$controller=new App\Http\Controllers\OperationsController();
loginAs($rider);DB::table('users')->where('id',$rider)->update(['online'=>0]);
$controller->availability(requestFor(['online'=>1]));
ok((int)DB::table('users')->find($rider)->online===1,'Go Active persists online state');
ok(DB::table('users')->find($rider)->last_seen_at!==null,'Go Active updates availability heartbeat');
loginAs($rider);$html=view('courier-availability')->render();ok(str_contains($html,'Active / Online')&&str_contains($html,'name="online" value="0"'),'Active UI offers explicit Not active action');
$controller->availability(requestFor(['online'=>0]));ok((int)DB::table('users')->find($rider)->online===0,'Go Not active persists offline state');
loginAs($rider);$html=view('courier-availability')->render();ok(str_contains($html,'Not active / Offline')&&str_contains($html,'Go Active'),'Inactive UI offers Active action');
ok((int)DB::table('users')->find($rider)->active===1,'Availability does not disable courier account');
$job=DB::table('assignments')->where('rider_id',$rider)->where('kind','delivery')->first();DB::table('assignments')->where('id',$job->id)->update(['status'=>'accepted']);$controller->availability(requestFor(['online'=>1]));
try{$controller->availability(requestFor(['online'=>0]));throw new RuntimeException('Offline with accepted job allowed');}catch(ValidationException $e){ok(isset($e->errors()['online']),'Accepted jobs block going Not active');}
DB::table('assignments')->where('id',$job->id)->update(['status'=>'completed']);
$driver=user('rider','availabilitydriver');DB::table('users')->where('id',$driver)->update(['courier_type'=>'driver']);loginAs($driver);$controller->availability(requestFor(['online'=>1]));ok((int)DB::table('users')->find($driver)->online===1,'Driver can also go Active');
loginAs($seller);try{$controller->availability(requestFor(['online'=>1]));throw new RuntimeException('Seller changed courier availability');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Non-courier cannot change work availability');}
echo 'PASS: '.($n-$before)." availability integration checks.\n";
