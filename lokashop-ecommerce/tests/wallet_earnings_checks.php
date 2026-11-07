<?php
// Builds a disposable SQLite fixture and runs the buyer wallet regression checks first.
require __DIR__.'/demo_wallet_checks.php';
use App\Services\{Wallet,WalletEarnings,Commerce,Workflow};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
$start=$n;
if(!Schema::hasColumn('assignments','handling_center_id'))Schema::table('assignments',fn(Blueprint $t)=>$t->unsignedBigInteger('handling_center_id')->nullable());
$admin=user('admin','walletadmin');$admin2=user('admin','walletadmin2');$driver=user('rider','walletdriver');DB::table('users')->where('id',$driver)->update(['courier_type'=>'driver','company_id'=>$company]);
function walletOrder(string $status='DELIVERED',string $method='cod',string $paymentStatus='paid'):int{global $buyer,$seller,$address; $id=DB::table('orders')->insertGetId(['buyer_id'=>$buyer,'seller_id'=>$seller,'address_id'=>$address,'status'=>$status,'total'=>150,'merchandise_total'=>100,'shipping_fee'=>50,'shipping_commission'=>10,'commission'=>10,'delivered_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);DB::table('payments')->insert(['order_id'=>$id,'method'=>$method,'status'=>$paymentStatus,'amount'=>150,'created_at'=>now(),'updated_at'=>now()]);DB::table('parcels')->insert(['order_id'=>$id,'tracking_code'=>'EARNINGS-'.$id,'status'=>$status,'created_at'=>now(),'updated_at'=>now()]);return $id;}
loginAs($buyer);$id=walletOrder();$before=Wallet::balance($seller);$pid=DB::table('parcels')->where('order_id',$id)->value('id');
Workflow::move($pid,'COMPLETED',$buyer);Wallet::release(DB::table('orders')->find($id));
ok(Wallet::balance($seller)===$before+9000,'COD seller earnings automatically reach wallet once');
ok(Wallet::balance($admin)===1000&&Wallet::balance($admin2)===0,'Platform commission reaches one admin wallet once');
ok(Commerce::available($seller)===0.0,'COD sale cannot be claimed as external payout after wallet credit');
$id=walletOrder('DELIVERED','cod','due_on_delivery');$before=Wallet::balance($seller);Wallet::release(DB::table('orders')->find($id));ok(Wallet::balance($seller)===$before,'Unpaid order cannot create wallet earnings');
$id=walletOrder('COMPLETED');ok(Commerce::available($seller)===90.0,'Previous uncredited COD earnings remain available');
$walletController=new App\Http\Controllers\WalletController;loginAs($seller);$before=Wallet::balance($seller);$r=requestFor(['password'=>'Test@1234','_request_key'=>(string)Str::uuid()]);$walletController->importSeller($r);$walletController->importSeller($r);
ok(Wallet::balance($seller)===$before+9000&&Commerce::available($seller)===0.0,'Legacy seller earnings import is exact and idempotent');
ok(DB::table('payouts')->where('seller_id',$seller)->where('account','LokaShop demo wallet')->count()===1,'Legacy import removes external payout eligibility');
// Destination and driver-company ownership cannot change which originating center pays.
$otherCenterUser=user('sorting_center','walletothercenter');$otherCenter=DB::table('sorting_centers')->insertGetId(['user_id'=>$otherCenterUser,'name'=>'Other center']);
$id=walletOrder('IN_TRANSIT_CENTER');$parcel=DB::table('parcels')->where('order_id',$id)->first();
$job=DB::table('assignments')->insertGetId(['handling_center_id'=>$center,'parcel_id'=>$parcel->id,'rider_id'=>$driver,'kind'=>'transfer','status'=>'accepted','earnings'=>50,'created_at'=>now(),'updated_at'=>now()]);
WalletEarnings::queue($job);ok(!DB::table('wallet_courier_payments')->where('assignment_id',$job)->exists(),'Uncompleted driver job is not payable');
DB::table('assignments')->where('id',$job)->update(['status'=>'completed']);DB::table('users')->where('id',$driver)->update(['company_id'=>$otherCenterUser]);WalletEarnings::queue($job);WalletEarnings::queue($job);
$row=DB::table('wallet_courier_payments')->where('assignment_id',$job)->first();ok($row->center_user_id===$company&&$row->status==='paid','Courier wallet is credited immediately; origin center settlement may remain outstanding');
ok(Wallet::balance($driver)===5000,'Completed driver earnings are spendable before center funding');
try{WalletEarnings::pay($job,$otherCenterUser);throw new RuntimeException('Foreign center paid another payroll');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===403,'Other center cannot pay or alter origin payroll');}
loginAs($company);$r=requestFor(['amount'=>'100.00','_request_key'=>(string)Str::uuid()]);$walletController->cashIn($r);$walletController->cashIn($r);
ok(Wallet::balance($driver)===5000&&Wallet::balance($company)===5000,'Center funding pays pending driver earnings exactly once');
WalletEarnings::pay($job,$company);ok(Wallet::balance($driver)===5000&&Wallet::balance($company)===5000,'Payment retry never double debits center');
$sent=DB::table('wallet_entries')->where('reference','courier-pay:'.$job)->value('amount_cents');$received=DB::table('wallet_entries')->where('reference','courier-earn:'.$job)->value('amount_cents');ok($sent+$received===0,'Courier payroll is a balanced transfer');
// A completed delivery records shipping income once and pays the recorded rider earning.
$beforeCenter=Wallet::balance($company);$beforeRider=Wallet::balance($rider);$id=walletOrder();$parcel=DB::table('parcels')->where('order_id',$id)->first();
$deliveryJob=DB::table('assignments')->insertGetId(['handling_center_id'=>$center,'parcel_id'=>$parcel->id,'rider_id'=>$rider,'kind'=>'delivery','status'=>'completed','earnings'=>40,'created_at'=>now(),'updated_at'=>now()]);
WalletEarnings::delivery($deliveryJob);WalletEarnings::delivery($deliveryJob);
ok(Wallet::balance($company)===$beforeCenter&&Wallet::balance($rider)===$beforeRider+4000,'Shipping income passes through center to rider once');
ok(DB::table('wallet_entries')->where('reference','shipping-income:'.$id)->value('amount_cents')===4000,'Shipping commission excluded from center income');
$pickupJob=DB::table('assignments')->insertGetId(['handling_center_id'=>$center,'parcel_id'=>$parcel->id,'rider_id'=>$rider,'kind'=>'pickup','status'=>'completed','earnings'=>0]);WalletEarnings::queue($pickupJob);ok(!DB::table('wallet_courier_payments')->where('assignment_id',$pickupJob)->exists(),'Free pickup creates no charge');
// Cross-role transfers support seller, admin, center, rider and driver wallets.
loginAs($company);$beforeAdmin=Wallet::balance($admin);Wallet::transfer($company,$admin,101,'cross-role-admin-0001');ok(Wallet::balance($admin)===$beforeAdmin+101,'Sorting center can send to admin wallet');
$beforeDriver=Wallet::balance($driver);Wallet::transfer($admin,$driver,100,'cross-role-driver-001');ok(Wallet::balance($driver)===$beforeDriver+100,'Admin can transfer to driver wallet');
// All new wallet pages render for their own role, including center payroll status.
view()->share('errors',new Illuminate\Support\ViewErrorBag());
foreach([$admin,$seller,$company,$driver] as $uid){loginAs($uid);$role=auth()->user()->role;$html=view('wallet',['links'=>app(App\Http\Controllers\CommonController::class)->links(),'balance'=>'10.00','legacy_earnings'=>0,'courier_payments'=>in_array($role,['sorting_center','rider'])?DB::table('wallet_courier_payments')->where($role==='sorting_center'?'center_user_id':'courier_user_id',$uid)->paginate(15):null,'transactions'=>DB::table('wallet_entries')->where('user_id',$uid)->paginate(20)])->render();ok(str_contains($html,'LokaShop Wallet'),'Role wallet renders: '.$role);}
// Import previous unpaid completed jobs without paying offers or another center's jobs.
loginAs($company);
$legacyJob=DB::table('assignments')->insertGetId(['handling_center_id'=>$center,'parcel_id'=>$parcel->id,'rider_id'=>$driver,'kind'=>'transfer','status'=>'completed','earnings'=>50,'created_at'=>now(),'updated_at'=>now()]);
invalid(fn()=>$walletController->importCourierJobs(requestFor(['password'=>'Test@1234','_request_key'=>(string)Str::uuid()])),'Historical job import requires confirmation that jobs were unpaid');
$r=requestFor(['password'=>'Test@1234','confirm_unpaid'=>1,'_request_key'=>(string)Str::uuid()]);$walletController->importCourierJobs($r);$walletController->importCourierJobs($r);
ok(DB::table('wallet_courier_payments')->where('assignment_id',$legacyJob)->count()===1,'Import cannot queue the same old job twice');
$foreign=DB::table('assignments')->insertGetId(['handling_center_id'=>$otherCenter,'parcel_id'=>$parcel->id,'rider_id'=>$driver,'kind'=>'transfer','status'=>'completed','earnings'=>50,'created_at'=>now(),'updated_at'=>now()]);
$walletController->importCourierJobs(requestFor(['password'=>'Test@1234','confirm_unpaid'=>1,'_request_key'=>(string)Str::uuid()]));ok(!DB::table('wallet_courier_payments')->where('assignment_id',$foreign)->exists(),'Center import cannot take another center payroll');
echo 'Passed '.($n-$start)." wallet earnings integration checks.\n";
