<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Auth};
use App\Http\Controllers\{AdminUserDetailsController,CommonController};
use App\Services\AdminUserDetails;
$before=$n;$logistics=config('app.site_kind')==='logistics';
$admin=user($logistics?'logistics_admin':'admin','profileadmin');
$driver=user('rider','profiledriver');DB::table('users')->where('id',$driver)->update(['courier_type'=>'driver','company_id'=>$company,'vehicle'=>'6-wheel truck','plate_number'=>'TEST-123','license_expires_at'=>'2027-10-25']);
DB::table('users')->where('id',$seller)->update(['remember_token'=>'DO_NOT_EXPOSE_TOKEN']);
DB::table('seller_businesses')->where('user_id',$seller)->update(['description'=>'A local demonstration store']);
$trip=DB::table('driver_trips')->insertGetId(['origin_center_id'=>$center,'driver_id'=>$driver,'status'=>'completed','completed_at'=>now(),'created_at'=>now()]);
loginAs($admin);$controller=new AdminUserDetailsController();
$targets=$logistics?[$company,$rider,$driver]:[$buyer,$seller];
$counts=[DB::table('users')->count(),DB::table('orders')->count(),DB::table('assignments')->count(),DB::table('driver_trips')->count()];
$rendered=[];
foreach($targets as $id){
 requestFor();$view=$controller->show(requestFor(),$id);$data=$view->getData()['details'];$html=$view->render();$rendered[$id]=$html;
 ok(str_contains($html,'Read-only details')&&str_contains($html,'Basic information'),'Admin can render basic details for account '.$id);
 ok(!str_contains($html,'DO_NOT_EXPOSE_TOKEN')&&!array_key_exists('password',$data),'Profile hides credentials and tokens');
 ok(!str_contains($html,'name="password"')&&!str_contains($html,'/admin/users/'.$id.'/status'),'Profile has no edit/status form');
}
ok($counts===[DB::table('users')->count(),DB::table('orders')->count(),DB::table('assignments')->count(),DB::table('driver_trips')->count()],'Viewing profiles does not insert business records');
if($logistics){
 $data=AdminUserDetails::forAccount($rider,true);$actual=DB::table('assignments')->where('rider_id',$rider)->where('kind','delivery')->where('status','completed')->distinct()->count('parcel_id');
 ok($data['stats']['Deliveries completed']===$actual&&$data['areaCount']>0,'Rider profile shows real completed delivery count and assigned areas');
 ok(str_contains($rendered[$company],'Sorting center details')&&str_contains($rendered[$company],'Service areas'),'Center profile has center and team details');
 ok(str_contains($rendered[$driver],'Recent transport trips')&&str_contains($rendered[$driver],'TEST-123'),'Driver profile includes vehicle and transport activity');
 $data=AdminUserDetails::forAccount($driver,true);ok($data['stats']['Completed trips']===1,'Driver completed trips come from transport records');
 foreach([$rider,$driver] as $uid){loginAs($uid);requestFor();foreach(['vehicle','availability'] as $module){$html=(new App\Http\Controllers\CourierSetupController())->settings(requestFor(),$module)->render();ok(!str_contains($html,'setup-tabs')&&str_contains($html,'class="sidebar"'),'Courier setup keeps sidebar and removes duplicate tab row');}}
}else{
 ok(str_contains($rendered[$seller],'Store details')&&str_contains($rendered[$seller],'A local demonstration store'),'Seller profile includes registered store details');
 ok(str_contains($rendered[$buyer],'Shopping activity'),'Buyer profile includes order activity');
 Auth::logout();requestFor();config(['logistics.url'=>'https://logistics.example.test']);$html=(new App\Http\Controllers\ShopController())->landing()->render();
 ok(str_contains($html,'href="https://logistics.example.test"')&&str_contains($html,'Become a seller'),'Landing links to configured logistics site and retains seller CTA');
 config(['logistics.url'=>null]);$request=Illuminate\Http\Request::create('http://192.168.1.15:8000/','GET');$app->instance('request',$request);$html=(new App\Http\Controllers\ShopController())->landing()->render();ok(str_contains($html,'href="http://192.168.1.15:8001"'),'Local logistics link uses current host on port 8001');
}
foreach([$buyer,$seller,$company,$rider,$driver] as $actor){loginAs($actor);try{$controller->show(requestFor(),$targets[0]);throw new RuntimeException('Non-admin opened profile');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Non-admin role cannot open profile: '.$actor);}}
loginAs($admin);foreach([$logistics?$buyer:$company,999999] as $id){try{$controller->show(requestFor(),$id);throw new RuntimeException('Out-of-scope profile opened');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===404,'Missing or other-system account stays unavailable');}}
requestFor();$list=$logistics?(new App\Http\Controllers\LogisticsAdminController())->users(requestFor()):(new App\Http\Controllers\AdminController())->users(requestFor());$html=$list->render();ok(str_contains($html,'View profile'),'Users list includes profile action');
$route=$app['router']->getRoutes()->match(Illuminate\Http\Request::create('/admin/users/'.$targets[0],'GET'));ok(in_array('role:'.($logistics?'logistics_admin':'admin'),$route->gatherMiddleware(),true),'Detail GET route requires correct administrator role');
// Optional local-only fixture HTML for visual inspection. Never user records.
$preview=getenv('ADMIN_PREVIEW_DIR');if($preview){foreach($rendered as $id=>$html)file_put_contents($preview.'/'.($logistics?'logi':'ecom').'-'.$id.'.html',$html);}
echo 'PASS: '.($n-$before)." admin profile, access and navigation checks.\n";
