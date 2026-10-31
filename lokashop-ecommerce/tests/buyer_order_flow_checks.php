<?php
// Disposable SQLite integration checks. Never connects to the project MySQL database.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Auth};
use Illuminate\Database\Schema\Blueprint;
use App\Services\{BuyerOrders,ParcelTracking};
use App\Http\Controllers\{ShopController,CommonController};
use App\Models\User;
use Illuminate\Http\Request;
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'session.driver'=>'array']);DB::purge();
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('role');});
Schema::create('orders',function(Blueprint $t){$t->id();$t->integer('buyer_id');$t->integer('seller_id');$t->integer('address_id')->nullable();$t->text('address_snapshot')->nullable();$t->string('status');$t->timestamps();});
Schema::create('parcels',function(Blueprint $t){$t->id();$t->integer('order_id');$t->string('status');$t->string('tracking_code');$t->text('failure_reason')->nullable();$t->string('proof_path')->nullable();$t->timestamps();});
Schema::create('seller_businesses',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('name');});
Schema::create('order_items',function(Blueprint $t){$t->id();$t->integer('order_id');$t->string('name');});
Schema::create('payments',function(Blueprint $t){$t->id();$t->integer('order_id');});
Schema::create('addresses',function(Blueprint $t){$t->id();});
Schema::create('status_history',function(Blueprint $t){$t->id();$t->integer('parcel_id');$t->integer('actor_id');$t->string('from_status')->nullable();$t->string('to_status');$t->text('reason')->nullable();$t->timestamps();});
Schema::create('assignments',function(Blueprint $t){$t->id();$t->integer('parcel_id');$t->string('kind');$t->timestamp('handed_over_at')->nullable();});
DB::table('users')->insert([['id'=>1,'name'=>'Buyer one','role'=>'buyer'],['id'=>2,'name'=>'Other buyer','role'=>'buyer'],['id'=>3,'name'=>'Seller','role'=>'seller']]);
DB::table('seller_businesses')->insert(['user_id'=>3,'name'=>'Demo store']);
foreach([1=>1,2=>2] as $id=>$buyer){DB::table('orders')->insert(['id'=>$id,'buyer_id'=>$buyer,'seller_id'=>3,'status'=>'PLACED','created_at'=>'2026-10-01 10:00:00','updated_at'=>'2026-10-01 10:00:00']);DB::table('parcels')->insert(['id'=>$id,'order_id'=>$id,'status'=>'PLACED','tracking_code'=>'TEST-'.$id,'created_at'=>'2026-10-01 10:00:00','updated_at'=>'2026-10-01 10:00:00']);DB::table('order_items')->insert(['order_id'=>$id,'name'=>'Order '.$id.' item']);}
Auth::setUser(User::find(1));$checks=0;
function check($test,$message){global $checks;if(!$test)throw new RuntimeException($message);$checks++;}
$controller=new ShopController;
foreach(['PLACED'=>'pending','CONFIRMED'=>'receive','PREPARING'=>'receive','READY_FOR_PICKUP'=>'receive','PICKED_UP'=>'receive','AT_SORTING_CENTER'=>'receive','TRANSFER_OFFERED'=>'receive','IN_TRANSIT_CENTER'=>'receive','SORTED'=>'receive','ASSIGNED_TO_RIDER'=>'receive','OUT_FOR_DELIVERY'=>'receive','DELIVERED'=>'completed','COMPLETED'=>'completed','CANCELLED'=>'completed','REFUNDED'=>'completed','RETURN_RECEIVED'=>'completed','RETURN_TO_SENDER'=>'receive'] as $status=>$tab){
 DB::table('orders')->where('id',1)->update(['status'=>$status]);
 $data=$controller->orders(Request::create('/orders','GET',['tab'=>$tab]))->getData();
 check($data['orders']->total()===1,"Visible in correct tab: $status");
 check($data['orders']->first()->id===1,'Other buyer must never appear');
 check(array_sum($data['counts'])===1,'Every order is in exactly one category');
 foreach(array_diff(['pending','receive','completed'],[$tab]) as $other){$q=BuyerOrders::filter(BuyerOrders::orders(1),$other);check($q->count()===0,"No duplicate in $other");}
}
$invalid=$controller->orders(Request::create('/orders','GET',['tab'=>'unknown']))->getData();check($invalid['tab']==='pending','Unknown tab falls back safely');
DB::table('orders')->where('id',1)->update(['status'=>'IN_TRANSIT_CENTER','updated_at'=>'2026-10-01 13:00:00']);DB::table('parcels')->where('id',1)->update(['status'=>'IN_TRANSIT_CENTER']);
foreach([['AT_SORTING_CENTER','11:00:00'],['IN_TRANSIT_CENTER','12:00:00'],['AT_SORTING_CENTER','12:30:00'],['IN_TRANSIT_CENTER','13:00:00']] as [$status,$time])DB::table('status_history')->insert(['parcel_id'=>1,'actor_id'=>3,'to_status'=>$status,'reason'=>'Recorded center event','created_at'=>'2026-10-01 '.$time,'updated_at'=>'2026-10-01 '.$time]);
DB::table('status_history')->insert(['parcel_id'=>2,'actor_id'=>3,'to_status'=>'DELIVERED','reason'=>'PRIVATE OTHER ORDER','created_at'=>'2026-10-01 14:00:00']);
ParcelTracking::note(1,3,'Rider accepted the pickup assignment.');
check(DB::table('orders')->where('id',1)->value('status')==='IN_TRANSIT_CENTER','Tracking note must not change order state');
check(DB::table('parcels')->where('id',1)->value('status')==='IN_TRANSIT_CENTER','Tracking note must not change parcel state');
DB::table('assignments')->insert(['parcel_id'=>1,'kind'=>'transfer','handed_over_at'=>'2026-10-01 13:30:00']);
$events=BuyerOrders::timeline(DB::table('orders')->find(1),DB::table('parcels')->find(1));
check($events->where('status','AT_SORTING_CENTER')->count()===2,'Repeated center arrivals retained');
check($events->where('status','DRIVER_HANDOVER')->count()===1,'Driver handover shown');
check($events->contains(fn($e)=>$e['title']==='Rider accepted the pickup assignment.'),'Operational event has readable title');
check(!$events->contains(fn($e)=>$e['detail']==='PRIVATE OTHER ORDER'),'History scoped to parcel');
check($events->last()['status']==='PLACED','Placement fallback present without invented intermediate steps');
$foreign=(new CommonController)->track('TEST-2');check($foreign->name()==='public-track','Other buyer gets only public tracking');check(!isset($foreign->getData()['items']),'No private items exposed');
echo "Passed $checks buyer order checks.\n";
