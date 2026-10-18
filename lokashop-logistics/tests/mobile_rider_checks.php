<?php
require __DIR__.'/delivery_proof_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\{MobileRiderController,RiderApiController,OperationsController};
$before=$n;
loginAs($rider);$mobile=new MobileRiderController();$api=new RiderApiController();
$overview=$mobile->overview(requestFor())->getData(true);
ok(isset($overview['cod_unremitted'],$overview['today_completed'],$overview['center']),'Overview includes operational totals and attached center');
$me=$api->me(requestFor())->getData(true)['rider'];
ok(array_key_exists('online',$me)&&array_key_exists('license_number',$me)&&isset($me['courier_type']),'Profile includes availability and vehicle documents');
ok(!isset($me['password'],$me['remember_token'],$me['delivery_otp_hash']),'Profile hides secret fields');
$rows=$api->assignments(requestFor())->getData(true);
ok(count($rows['data'])<=12&&isset($rows['current_page'],$rows['last_page']),'Assignment list has twelve rows per page');
$job=DB::table('assignments')->where('rider_id',$rider)->where('kind','delivery')->where('status','completed')->first();
$detail=$mobile->detail(requestFor(),$job->id)->getData(true);
ok(count($detail['events'])>0&&$detail['proof_url'],'Owned completed delivery exposes timeline and authenticated proof link');
$proof=$mobile->proof($job->id);
ok($proof instanceof Symfony\Component\HttpFoundation\BinaryFileResponse&&str_contains($proof->headers->get('Cache-Control'),'no-store'),'Rider proof response is private');
$stranger=user('rider','mobile-stranger');loginAs($stranger);
foreach(['detail','proof'] as $method){try{if($method==='detail')$mobile->detail(requestFor(),$job->id);else $mobile->proof($job->id);throw new RuntimeException('Unrelated courier accessed parcel');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===404,'Another courier cannot access '.$method);}}
loginAs($rider);
$chat=$mobile->messages(requestFor())->getData(true);
ok(collect($chat['users'])->contains('id',$company),'Courier chat includes approved attached sorting center');
(new App\Http\Controllers\CommonController())->sendMessage(requestFor(['recipient_id'=>$company,'body'=>'Native courier integration test']));
$chat=$mobile->messages(requestFor(['with'=>$company]))->getData(true);
ok(collect($chat['messages'])->contains('body','Native courier integration test'),'Native message uses existing web conversation');
try{(new App\Http\Controllers\CommonController())->sendMessage(requestFor(['recipient_id'=>$seller,'body'=>'Not allowed']));throw new RuntimeException('Courier messaged unrelated seller');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Courier cannot message unrelated roles');}
$notice=DB::table('notifications')->insertGetId(['user_id'=>$rider,'body'=>'Native notice']);
$otherNotice=DB::table('notifications')->insertGetId(['user_id'=>$stranger,'body'=>'Private notice']);
$mobile->read(requestFor());ok(DB::table('notifications')->find($notice)->read_at&&!DB::table('notifications')->find($otherNotice)->read_at,'Mark read affects only current courier');
$cod=$mobile->cod(requestFor())->getData(true);ok(isset($cod['unremitted'],$cod['records']['last_page']),'COD summary includes outstanding funds and pagination');
$amount=(float)DB::table('cod_collections')->where('rider_id',$rider)->whereNull('remittance_id')->sum('amount');
$ops=new OperationsController();$request=requestFor(['amount'=>$amount,'reference'=>'Mobile remittance','_request_key'=>'native-remit-check']);
$ops->remit($request);$ops->remit($request);
ok(DB::table('remittances')->where('rider_id',$rider)->where('reference','Mobile remittance')->count()===1,'Native remittance uses server idempotency');
$routes=$app['router']->getRoutes();foreach(['/api/rider/overview','/api/rider/cod','/api/rider/messages','/api/rider/notifications','/api/rider/assignments/'.$job->id.'/proof'] as $path){$route=$routes->match(Illuminate\Http\Request::create($path,'GET'));ok(in_array('rider.api',$route->gatherMiddleware(),true),'Mobile read route requires bearer courier authentication');}
// Complete a fresh parcel through the native controller with a multipart proof.
loginAs($buyer);App\Services\Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));
$ids=App\Services\Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>'mobile-delivery-fixture']));
$orderId=$ids[0];$parcel=DB::table('parcels')->where('order_id',$orderId)->first();
DB::table('parcels')->where('id',$parcel->id)->update(['status'=>'ASSIGNED_TO_RIDER','center_id'=>$center]);
DB::table('orders')->where('id',$orderId)->update(['status'=>'ASSIGNED_TO_RIDER']);
DB::table('assignments')->insert(['rider_id'=>$rider,'parcel_id'=>$parcel->id,'kind'=>'delivery','status'=>'accepted']);
loginAs($rider);$api->status(requestFor(['status'=>'OUT_FOR_DELIVERY']),$parcel->id);
ok(DB::table('parcels')->find($parcel->id)->delivery_otp_hash!==null,'Native out-for-delivery creates buyer OTP');
DB::table('parcels')->where('id',$parcel->id)->update(['delivery_otp_hash'=>Illuminate\Support\Facades\Hash::make('123456')]);
$upload=tempnam(sys_get_temp_dir(),'native-proof');file_put_contents($upload,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD9sAAAAASUVORK5CYII='));
$amount=DB::table('orders')->find($orderId)->total;
$fields=['status'=>'DELIVERED','otp'=>'123456','cod_amount'=>$amount,'scanned_code'=>$parcel->tracking_code];
try{$api->status(requestFor($fields),$parcel->id);throw new RuntimeException('Native delivery accepted without proof');}catch(Illuminate\Validation\ValidationException $e){ok(isset($e->errors()['proof']),'Native delivery rejects missing proof');}
$api->status(requestFor($fields,['proof'=>new UploadedFile($upload,'proof.png','image/png',null,true)]),$parcel->id);
$delivered=DB::table('parcels')->find($parcel->id);
ok($delivered->status==='DELIVERED'&&Illuminate\Support\Facades\Storage::disk('local')->exists($delivered->proof_path),'Native delivery persists buyer-visible proof');
ok(DB::table('cod_collections')->where('parcel_id',$parcel->id)->where('rider_id',$rider)->value('amount')==$amount,'Native delivery records exact COD collection');
loginAs($buyer);App\Services\Workflow::move($parcel->id,'COMPLETED',$buyer);
ok(DB::table('orders')->find($orderId)->status==='COMPLETED','Buyer can confirm delivery made through native rider API');
@unlink($upload);
echo 'PASS: '.($n-$before)." native rider API checks.\n";
