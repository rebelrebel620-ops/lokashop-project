<?php
// Disposable integration checks. Does not connect to your MySQL database.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Auth,Hash};
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Validation\ValidationException;
use App\Services\{Commerce,Workflow,Dispatch,Cart,Support};
use App\Models\User;
config(['app.key'=>'base64:'.base64_encode(str_repeat('T',32)),'database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'session.driver'=>'array','hashing.bcrypt.rounds'=>4,'filesystems.disks.local.root'=>sys_get_temp_dir().'/lokashop-tests-private','filesystems.disks.public.root'=>sys_get_temp_dir().'/lokashop-tests-public']);DB::purge();
Illuminate\Support\Carbon::setTestNow('2026-10-15 12:00:00');
$sql=file_get_contents(__DIR__.'/../database/schema/lokashop_schema.sql');$sql=str_replace('BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);DB::unprepared($sql);
foreach(['2026_10_10_000001_registration_validation_and_otp.php','2026_10_15_000003_marketplace_workflows.php'] as $file){$m=require __DIR__.'/../database/migrations/'.$file;$m->up();}
// A second pass must not add duplicate columns or tables.
$m->up();
$m4=require __DIR__.'/../database/migrations/2026_10_15_000004_usability_and_territories.php';$m4->up();
foreach(glob(__DIR__.'/../database/migrations/2026_10_16_*.php') as $file){$new=require $file;$new->up();}
$latest=require __DIR__.'/../database/migrations/2026_10_23_000001_municipal_pickup_and_commission.php';$latest->up();
$cycleMigration=require __DIR__.'/../database/migrations/2026_10_23_000002_pickup_acceptance_window.php';$cycleMigration->up();
$n=0;function ok($x,$label){global $n;if(!$x)throw new RuntimeException('FAIL: '.$label);$n++;}
function requestFor(array $data=[],array $files=[]):Request{global $app;$r=Request::create('/test','POST',$data,[], $files,['HTTP_ACCEPT'=>'application/json']);$r->setLaravelSession($app['session']->driver());$r->setUserResolver(fn()=>Auth::user());$app->instance('request',$r);return $r;}
function user(string $role,string $name){$id=DB::table('users')->insertGetId(['name'=>$name,'email'=>$name.'@example.test','password'=>Hash::make('Test@1234'),'role'=>$role,'approval_status'=>'approved','active'=>1,'last_seen_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return $id;}
function loginAs(int $id){Auth::setUser(User::find($id));}
$buyer=user('buyer','buyer');$other=user('buyer','other');$seller=user('seller','seller');$company=user('sorting_center','company');$rider=user('rider','rider');DB::table('users')->where('id',$rider)->update(['company_id'=>$company,'online'=>1]);
DB::table('seller_businesses')->insert(['user_id'=>$seller,'name'=>'Test store']);$center=DB::table('sorting_centers')->insertGetId(['user_id'=>$company,'name'=>'Center']);DB::table('shipping_rates')->insert(['center_id'=>$center,'city'=>'Quezon City','province'=>'Metro Manila','base_fee'=>65,'extra_kg_fee'=>20,'active'=>1]);
$address=DB::table('addresses')->insertGetId(['user_id'=>$buyer,'recipient'=>'Buyer','phone'=>'09171234567','street'=>'Private Street','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','postal_code'=>'1121','is_default'=>1]);$cat=DB::table('categories')->insertGetId(['name'=>'Test category']);$p=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Product one','description'=>str_repeat('Description ',3),'price'=>100,'stock'=>5,'approved'=>1,'active'=>1,'weight'=>.5]);
DB::table('sorting_centers')->where('id',$center)->update(['city'=>'Quezon City','province'=>'Metro Manila','municipality_key'=>App\Services\Municipalities::key('Metro Manila','Quezon City')]);foreach([$seller,$rider] as $uid)DB::table('addresses')->insert(['user_id'=>$uid,'recipient'=>'Fixture','phone'=>'09170000000','street'=>'Origin','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);
DB::table('delivery_areas')->insert(['center_id'=>$center,'name'=>'Pickup fixture','city'=>'Quezon City','province'=>'Metro Manila','barangay'=>'Commonwealth','rider_id'=>$rider]);
loginAs($buyer);
foreach([3,3] as $qty)DB::table('cart_items')->insert(['buyer_id'=>$buyer,'product_id'=>$p,'quantity'=>$qty]);
try{Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));throw new RuntimeException('Overselling accepted');}catch(ValidationException $e){ok(isset($e->errors()['stock']),'Duplicate rows aggregate stock');}
ok(DB::table('products')->find($p)->stock==5,'Failed checkout leaves stock unchanged');ok(DB::table('orders')->count()===0,'Failed checkout creates no order');
DB::table('cart_items')->delete();Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));ok(DB::table('cart_items')->count()===1,'Repeated product adds merge');ok(DB::table('cart_items')->first()->quantity==2,'Merged quantity correct');
$key=(string)Illuminate\Support\Str::uuid();$r=requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>$key]);$ids=Commerce::checkout($r);$oid=$ids[0];$o=DB::table('orders')->find($oid);
ok($o->total==265,'Shipping included in total');ok($o->commission==20,'Commission is ten percent of merchandise');ok(DB::table('products')->find($p)->stock==3,'Stock deducted once');ok(Commerce::checkout($r)===$ids,'Checkout key replay returns same result');ok(DB::table('orders')->count()===1,'Key replay creates no duplicate');
loginAs($other);try{Commerce::cancel($oid,'Test cancellation reason',$other);throw new RuntimeException('Wrong buyer accepted');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Wrong buyer cannot cancel');}
loginAs($buyer);Commerce::cancel($oid,'Test cancellation reason',$buyer);Commerce::cancel($oid,'Test cancellation reason',$buyer);ok(DB::table('products')->find($p)->stock==5,'Cancellation restores stock exactly once');ok(DB::table('orders')->find($oid)->status==='CANCELLED','Cancellation state saved');
Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));$oid=$ids[0];$parcel=DB::table('parcels')->where('order_id',$oid)->first();$pid=$parcel->id;loginAs($seller);Workflow::move($pid,'CONFIRMED',$seller);Workflow::move($pid,'PREPARING',$seller);DB::table('parcels')->where('id',$pid)->update(['center_id'=>$center]);$assignment=DB::table('assignments')->insertGetId(['parcel_id'=>$pid,'rider_id'=>$rider,'kind'=>'pickup','status'=>'offered','expires_at'=>now()->addMinute(),'earnings'=>30]);Workflow::move($pid,'READY_FOR_PICKUP',$seller);loginAs($rider);Dispatch::accept($assignment,$rider);Dispatch::accept($assignment,$rider);Workflow::move($pid,'PICKED_UP',$rider);loginAs($company);Workflow::move($pid,'AT_SORTING_CENTER',$company,null,null,$center);$area=DB::table('delivery_areas')->insertGetId(['center_id'=>$center,'name'=>'QC','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','rider_id'=>$rider]);Workflow::move($pid,'SORTED',$company,null,$area,$center);Dispatch::offer($pid,$company,$rider);$a=DB::table('assignments')->where('parcel_id',$pid)->where('kind','delivery')->first();loginAs($rider);Dispatch::accept($a->id,$rider);requestFor();Workflow::move($pid,'OUT_FOR_DELIVERY',$rider);ok(DB::table('parcels')->find($pid)->delivery_otp_hash!==null,'OTP generated at out-for-delivery');
DB::table('parcels')->where('id',$pid)->update(['delivery_otp_hash'=>Hash::make('123456')]);
$tmp=tempnam(sys_get_temp_dir(),'proof');file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD9sAAAAASUVORK5CYII='));
for($i=0;$i<5;$i++){
 requestFor(['otp'=>'000000','cod_amount'=>165],['proof'=>new UploadedFile($tmp,'proof.png','image/png',null,true)]);
 try{Workflow::move($pid,'DELIVERED',$rider);throw new RuntimeException('Bad OTP accepted');}catch(ValidationException $e){ok(isset($e->errors()['otp']),'Bad OTP blocked');}
}
ok(DB::table('parcels')->find($pid)->otp_attempts===5,'OTP attempt count persists across rollbacks');ok(DB::table('cod_collections')->count()===0,'Bad OTP produces no COD entry');
requestFor(['otp'=>'123456','cod_amount'=>165],['proof'=>new UploadedFile($tmp,'proof.png','image/png',null,true)]);try{Workflow::move($pid,'DELIVERED',$rider);throw new RuntimeException('Lock ignored');}catch(ValidationException $e){ok(isset($e->errors()['otp']),'Correct OTP blocked during lock');}
Illuminate\Support\Carbon::setTestNow(now()->addMinutes(16));DB::table('users')->where('id',$rider)->update(['last_seen_at'=>now()]);requestFor(['otp'=>'123456','cod_amount'=>165],['proof'=>new UploadedFile($tmp,'proof.png','image/png',null,true)]);Workflow::move($pid,'DELIVERED',$rider);
ok(DB::table('orders')->find($oid)->status==='DELIVERED','Delivery completed with proof/OTP/COD');ok(DB::table('payments')->where('order_id',$oid)->value('status')==='paid','COD payment settled');ok(DB::table('cod_collections')->sum('amount')==165,'COD ledger recorded exact amount');ok(DB::table('assignments')->where('id',$a->id)->value('status')==='completed','Delivery earnings released once');Workflow::move($pid,'DELIVERED',$rider);ok(DB::table('cod_collections')->count()===1,'Delivery retry creates no duplicate COD collection');loginAs($buyer);Workflow::move($pid,'COMPLETED',$buyer);ok(Commerce::available($seller)==90,'Seller available earnings exclude shipping and use 10%');
$alloc=Commerce::allocate(100,[1=>649,2=>399]);ok(array_sum($alloc)==100,'Discount allocation reconciles exactly');
// Render the new tools screen against a real Laravel view engine.
view()->share('errors',new \Illuminate\Support\ViewErrorBag());$r=requestFor();$response=view('tools',['title'=>'Test tools','links'=>[],'records'=>collect([['id'=>1,'status'=>'test']]),'forms'=>[['label'=>'Test','action'=>'/tools/test','fields'=>['id?','amount:number','photos:file[]?','active:checkbox']]]]);$html=$response->render();ok(str_contains($html,'Test tools'),'New page renders');


// The workbook's worked checkout: 699 + 399 merchandise, 65 + 120 shipping,
// less 50 seller, 100 platform and 50 shipping discount = 1,083.
$s2=user('seller','seller2');DB::table('seller_businesses')->insert(['user_id'=>$s2,'name'=>'Second store']);DB::table('addresses')->insert(['user_id'=>$s2,'recipient'=>'Seller 2','phone'=>'09170000000','street'=>'Origin','barangay'=>'Center','city'=>'Cebu City','province'=>'Cebu','is_default'=>1]);DB::table('shipping_rates')->insert(['center_id'=>$center,'origin_province'=>'Cebu','city'=>'Quezon City','province'=>'Metro Manila','base_fee'=>120,'extra_kg_fee'=>20,'active'=>1]);
$pa=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Worked item A','price'=>699,'stock'=>10,'approved'=>1,'active'=>1,'weight'=>1]);$pb=DB::table('products')->insertGetId(['seller_id'=>$s2,'category_id'=>$cat,'name'=>'Worked item B','price'=>399,'stock'=>10,'approved'=>1,'active'=>1,'weight'=>1]);
foreach([['MCH50',$seller,'fixed',50],['PLAT100',null,'fixed',100],['FREESHIP',null,'shipping',50]] as [$code,$sid,$type,$value]){$vid=DB::table('vouchers')->insertGetId(['code'=>$code,'seller_id'=>$sid,'type'=>$type,'value'=>$value,'discount_percent'=>0,'active'=>1,'claimable'=>1,'usage_limit'=>10,'per_buyer_limit'=>3]);DB::table('voucher_claims')->insert(['buyer_id'=>$buyer,'voucher_id'=>$vid]);}
loginAs($buyer);Cart::add(requestFor(['product_id'=>$pa,'quantity'=>1]));Cart::add(requestFor(['product_id'=>$pb,'quantity'=>1]));$worked=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','voucher'=>'MCH50','platform_voucher'=>'PLAT100','shipping_voucher'=>'FREESHIP','_request_key'=>(string)Illuminate\Support\Str::uuid()]));ok(count($worked)===2,'Worked checkout splits by seller');ok(DB::table('orders')->whereIn('id',$worked)->sum('total')==1083,'Worked total is PHP 1083');ok(DB::table('orders')->whereIn('id',$worked)->where('seller_id',$seller)->value('commission')==64.9,'Worked seller commission is PHP 64.90');ok(DB::table('vouchers')->where('code','PLAT100')->value('used_count')==1,'Platform voucher counted once across stores');Commerce::cancel($worked[0],'Cancellation in integration test',$buyer);ok(DB::table('vouchers')->where('code','PLAT100')->value('used_count')==1,'Partial checkout cancellation preserves shared redemption');Commerce::cancel($worked[1],'Cancellation in integration test',$buyer);ok(DB::table('vouchers')->where('code','PLAT100')->value('used_count')==0,'Full checkout cancellation restores shared redemption once');

// Exercise every newly added role panel with the real Blade renderer.
if(config('app.site_kind')==='ecommerce'){
 $controller=new \App\Http\Controllers\MarketController();
 foreach([[$buyer,['addresses','wishlist','vouchers','returns','ratings']],[$seller,['store','promotions','products','returns','earnings','ratings']]] as [$uid,$modules]){loginAs($uid);foreach($modules as $module){$panel=$controller->panel(requestFor(),$module);if($panel instanceof \Illuminate\Http\RedirectResponse){ok($panel->getStatusCode()===302,'Dedicated route for '.$module.' panel');}else{$html=$panel->render();ok(strlen($html)>100,'Rendered '.$module.' panel');}}}
 $admin=user('admin','admin');loginAs($admin);foreach(['payments','platform'] as $module){ok(strlen($controller->panel(requestFor(),$module)->render())>100,'Rendered admin '.$module);}
 // Public tracking must not render buyer address or phone.
 Auth::logout();$html=(new \App\Http\Controllers\CommonController())->track(DB::table('parcels')->find($pid)->tracking_code)->render();ok(!str_contains($html,'Private Street')&&!str_contains($html,'09171234567'),'Public tracking conceals private details');
 loginAs($seller);$report=new \App\Http\Controllers\ReportController();ok(strlen($report->index(requestFor())->render())>100,'Sales report renders');
 $xr=requestFor(['format'=>'xlsx']);$file=$report->index($xr);$zip=new ZipArchive();ok($zip->open($file->getFile()->getPathname())===true,'XLSX export is a readable ZIP');ok($zip->locateName('xl/worksheets/sheet1.xml')!==false,'XLSX export contains worksheet');$zip->close();@unlink($file->getFile()->getPathname());
}else{
 $controller=new \App\Http\Controllers\OperationsController();
 loginAs($company);foreach(['riders','rates','cod','tracking','returns','overview'] as $module){ok(strlen($controller->panel(requestFor(),$module)->render())>100,'Rendered company '.$module);}
 loginAs($rider);foreach(['availability','cod','vehicle'] as $module){ok(strlen($controller->panel(requestFor(),$module)->render())>100,'Rendered rider '.$module);}
 ok(strlen((new \App\Http\Controllers\CompanyRegistrationController())->form()->render())>100,'Company signup renders');
 $admin=user('logistics_admin','logadmin');loginAs($admin);ok(strlen($controller->panel(requestFor(),'legacy')->render())>100,'Legacy linking panel renders');
}

@unlink($tmp);echo "PASS: {$n} workflow checks (SQLite; disposable database).\n";
