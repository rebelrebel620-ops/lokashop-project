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
$bulkMigration=require __DIR__.'/../database/migrations/2026_10_24_000001_bulk_driver_trips.php';$bulkMigration->up();$bulkMigration->up();
$handoverMigration=require __DIR__.'/../database/migrations/2026_10_25_000001_transport_handover_confirmation.php';$handoverMigration->up();$handoverMigration->up();
$n=0;function ok($x,$label){global $n;if(!$x)throw new RuntimeException('FAIL: '.$label);$n++;}
function requestFor(array $data=[],array $files=[]):Request{global $app;$r=Request::create('/test','POST',$data,[], $files,['HTTP_ACCEPT'=>'application/json']);$r->setLaravelSession($app['session']->driver());$r->setUserResolver(fn()=>Auth::user());$app->instance('request',$r);return $r;}
function user(string $role,string $name){$id=DB::table('users')->insertGetId(['name'=>$name,'email'=>$name.'@example.test','password'=>Hash::make('Test@1234'),'role'=>$role,'approval_status'=>'approved','active'=>1,'last_seen_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return $id;}
function loginAs(int $id){Auth::setUser(User::find($id));}
// Run against disposable in-memory SQLite only.
$walletMigration=require __DIR__.'/../database/migrations/2026_11_06_000001_demo_wallet.php';$walletMigration->up();$walletMigration->up();$payrollMigration=require __DIR__.'/../database/migrations/2026_11_06_000002_wallet_courier_payments.php';$payrollMigration->up();$payrollMigration->up();
use App\Services\Wallet;
$buyer=user('buyer','buyer');$other=user('buyer','other');$seller=user('seller','seller');$company=user('sorting_center','company');
DB::table('seller_businesses')->insert(['user_id'=>$seller,'name'=>'Test store']);
$center=DB::table('sorting_centers')->insertGetId(['user_id'=>$company,'name'=>'Center','city'=>'Quezon City','province'=>'Metro Manila','municipality_key'=>App\Services\Municipalities::key('Metro Manila','Quezon City')]);
$address=DB::table('addresses')->insertGetId(['user_id'=>$buyer,'recipient'=>'Buyer','phone'=>'09171234567','street'=>'Test','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','postal_code'=>'1121','is_default'=>1]);
$cat=DB::table('categories')->insertGetId(['name'=>'Test category']);
$p=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Product','description'=>'Test product','price'=>100,'stock'=>10,'approved'=>1,'active'=>1,'weight'=>.5]);
loginAs($buyer);
function invalid(callable $fn,string $label){try{$fn();}catch(ValidationException $e){ok(true,$label);return;}throw new RuntimeException('FAIL: '.$label);}
ok(Wallet::balance($buyer)===0,'New wallet starts at zero');
Wallet::cashIn($buyer,100000,'funding-key-00001');Wallet::cashIn($buyer,100000,'funding-key-00001');
ok(Wallet::balance($buyer)===100000,'Cash-in retry is idempotent');
Wallet::transfer($buyer,$other,12345,'transfer-key-0001');Wallet::transfer($buyer,$other,12345,'transfer-key-0001');
ok(Wallet::balance($buyer)===87655&&Wallet::balance($other)===12345,'Transfer is exact and idempotent');
Wallet::transfer($buyer,$seller,12345,'transfer-key-0001');ok(Wallet::balance($seller)===0,'Changed-recipient replay cannot mint funds');
invalid(fn()=>Wallet::transfer($buyer,$buyer,100,'self-key-0000001'),'Self transfer rejected');
invalid(fn()=>Wallet::transfer($buyer,$other,9999999,'overdraw-key-0001'),'Insufficient funds rejected');
ok(Wallet::balance($other)===12345,'Failed transfer rolls back recipient');
DB::table('users')->where('id',$other)->update(['account_status'=>'suspended']);
invalid(fn()=>Wallet::transfer($buyer,$other,100,'suspended-key-01'),'Suspended recipient rejected');
DB::table('users')->where('id',$other)->update(['account_status'=>'active']);
foreach(['-1','1.001','1e3'] as $bad)invalid(fn()=>Wallet::cents($bad),'Invalid centavo amount rejected');
ok(Wallet::cents('10.01')===1001,'Centavos preserve precision');
Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));
$before=Wallet::balance($buyer);$key=(string)Illuminate\Support\Str::uuid();
$r=requestFor(['address_id'=>$address,'payment_method'=>'wallet','_request_key'=>$key]);
// Logistics has no buyer quote endpoint; test its shared order lifecycle directly.
$ids=Commerce::checkout($r);$oid=$ids[0];$o=DB::table('orders')->find($oid);
ok($o->total==150,'Fallback shipping included');
ok(Wallet::balance($buyer)===$before-15000,'Checkout deducts exact order total');
ok(DB::table('payments')->where('order_id',$oid)->value('status')==='paid','Wallet payment is prepaid');
ok(Commerce::checkout($r)===$ids&&Wallet::balance($buyer)===$before-15000,'Checkout replay does not double charge');
Commerce::cancel($oid,'Wallet test',$buyer);Commerce::cancel($oid,'Wallet test',$buyer);
ok(Wallet::balance($buyer)===$before,'Cancellation refunds once');
ok(DB::table('payments')->where('order_id',$oid)->value('status')==='refunded','Payment marked refunded');
Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));
DB::table('wallet_accounts')->where('user_id',$buyer)->update(['balance_cents'=>0]);
$stock=DB::table('products')->where('id',$p)->value('stock');$count=DB::table('orders')->count();
invalid(fn()=>Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'wallet','_request_key'=>(string)Illuminate\Support\Str::uuid()])),'Unfunded checkout rejected');
ok(DB::table('orders')->count()===$count&&DB::table('products')->where('id',$p)->value('stock')===$stock&&DB::table('cart_items')->count()===1,'Rejected checkout rolls back orders stock and cart');
Wallet::cashIn($buyer,100000,'more-funding-0001');
$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'wallet','_request_key'=>(string)Illuminate\Support\Str::uuid()]));$oid=$ids[0];
DB::table('orders')->where('id',$oid)->update(['status'=>'DELIVERED','delivered_at'=>now()]);DB::table('parcels')->where('order_id',$oid)->update(['status'=>'DELIVERED']);
$pid=DB::table('parcels')->where('order_id',$oid)->value('id');
Workflow::move($pid,'COMPLETED',$buyer);Workflow::move($pid,'COMPLETED',$buyer);
ok(Wallet::balance($seller)===9000,'Seller receives earnings once after completion');
ok(Commerce::available($seller)===0.0,'Wallet earnings excluded from external payout pool');
invalid(fn()=>Wallet::refund(DB::table('orders')->find($oid)),'Settled order cannot refund twice');
// Two-product checkout: debit for the first order must roll back if the second cannot be paid.
Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));
$p2=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Second product','description'=>'Test','price'=>100,'stock'=>5,'approved'=>1,'active'=>1,'weight'=>.5]);Cart::add(requestFor(['product_id'=>$p2,'quantity'=>1]));
DB::table('wallet_accounts')->where('user_id',$buyer)->update(['balance_cents'=>15000]);$count=DB::table('orders')->count();$entries=DB::table('wallet_entries')->count();
invalid(fn()=>Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'wallet','_request_key'=>(string)Illuminate\Support\Str::uuid()])),'Partial multi-order checkout rolls back');
ok(Wallet::balance($buyer)===15000&&DB::table('orders')->count()===$count&&DB::table('wallet_entries')->count()===$entries,'No partial charge or partial order remains');
DB::table('cart_items')->where('product_id',$p2)->delete();
$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));
ok(Wallet::balance($buyer)===15000&&DB::table('payments')->where('order_id',$ids[0])->value('status')==='due_on_delivery','COD leaves wallet unchanged');
// Exercise delivery proof for a real prepaid order: no COD collection is created.
Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));
$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'wallet','_request_key'=>(string)Illuminate\Support\Str::uuid()]));$deliveryOrder=$ids[0];
$rider=user('rider','wallet-rider');DB::table('users')->where('id',$rider)->update(['company_id'=>$company,'online'=>1]);
foreach([$seller,$rider] as $uid)DB::table('addresses')->insert(['user_id'=>$uid,'recipient'=>'Fixture','phone'=>'09170000000','street'=>'Origin','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);
$area=DB::table('delivery_areas')->insertGetId(['center_id'=>$center,'name'=>'Wallet delivery area','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','rider_id'=>$rider]);
DB::table('orders')->where('id',$deliveryOrder)->update(['status'=>'OUT_FOR_DELIVERY']);DB::table('parcels')->where('order_id',$deliveryOrder)->update(['status'=>'OUT_FOR_DELIVERY','center_id'=>$center,'delivery_area_id'=>$area]);
$parcel=DB::table('parcels')->where('order_id',$deliveryOrder)->first();
DB::table('assignments')->insert(['parcel_id'=>$parcel->id,'rider_id'=>$rider,'kind'=>'delivery','status'=>'accepted','earnings'=>40,'created_at'=>now(),'updated_at'=>now()]);
loginAs($rider);requestFor(['scanned_code'=>$parcel->tracking_code]);
invalid(fn()=>Workflow::move($parcel->id,'DELIVERED',$rider),'Prepaid delivery still requires proof');
$tmp=tempnam(sys_get_temp_dir(),'wallet-proof');file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD9sAAAAASUVORK5CYII='));
requestFor(['scanned_code'=>$parcel->tracking_code],['proof'=>new UploadedFile($tmp,'proof.png','image/png',null,true)]);Workflow::move($parcel->id,'DELIVERED',$rider);unlink($tmp);
ok(DB::table('orders')->where('id',$deliveryOrder)->value('status')==='DELIVERED'&&DB::table('cod_collections')->count()===0,'Prepaid delivery records no COD collection');
ok(Wallet::balance($seller)===9000,'Delivery alone keeps seller funds on hold');
loginAs($buyer);Workflow::move($parcel->id,'COMPLETED',$buyer);ok(Wallet::balance($seller)===18000,'Buyer confirmation releases prepaid earnings');
echo "Passed $n logistics wallet checks.\n";
