<?php
require __DIR__.'/demo_wallet_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\{Wallet,WalletEarnings};
use App\Http\Controllers\{WalletController,CourierFinancialController};
$begin=$n;
$owner=user('sorting_center','immediatecenter');DB::table('sorting_centers')->insert(['user_id'=>$owner,'name'=>'Immediate center']);
$earner=user('rider','immediatedriver');DB::table('users')->where('id',$earner)->update(['courier_type'=>'driver','company_id'=>$owner]);
$o=DB::table('orders')->first();$p=DB::table('parcels')->where('order_id',$o->id)->first();
for($i=0;$i<8;$i++)DB::table('assignments')->insert(['rider_id'=>$earner,'parcel_id'=>$p->id,'kind'=>'transfer','status'=>'completed','earnings'=>50,'created_at'=>now(),'updated_at'=>now()]);
loginAs($earner);$controller=new WalletController;$request=requestFor(['_request_key'=>(string)Str::uuid()]);$controller->creditCourierEarnings($request);$controller->creditCourierEarnings($request);
ok(Wallet::balance($earner)===40000,'Eight previous PHP 50 jobs credit PHP 400 immediately');
ok(Wallet::balance($owner)===0,'Unfunded center wallet never becomes negative');
ok((int)WalletEarnings::unsettled()->where('center_user_id',$owner)->sum('amount_cents')===40000,'Center obligation is tracked separately from courier funds');
$controller->creditCourierEarnings(requestFor(['_request_key'=>(string)Str::uuid()]));ok(Wallet::balance($earner)===40000,'Fresh-key retry cannot duplicate earnings');
$report=(new CourierFinancialController)->index(requestFor())->getData();ok((float)$report['stats']->earned===400.0&&(float)$report['stats']->paid===400.0&&(float)$report['stats']->unpaid===0.0,'Report shows all PHP 400 as paid to wallet');
$job=DB::table('assignments')->insertGetId(['rider_id'=>$earner,'parcel_id'=>$p->id,'kind'=>'transfer','status'=>'completed','earnings'=>50,'created_at'=>now(),'updated_at'=>now()]);WalletEarnings::queue($job);WalletEarnings::queue($job);ok(Wallet::balance($earner)===45000,'New completed job credits automatically and only once');
$incomplete=DB::table('assignments')->insertGetId(['rider_id'=>$earner,'parcel_id'=>$p->id,'kind'=>'transfer','status'=>'accepted','earnings'=>500,'created_at'=>now(),'updated_at'=>now()]);WalletEarnings::syncCourier($earner);ok(Wallet::balance($earner)===45000,'Accepted job does not count as earned money');
loginAs($owner);$controller->cashIn(requestFor(['amount'=>'1000.00','_request_key'=>(string)Str::uuid()]));ok(Wallet::balance($owner)===55000,'Funding settles exact PHP 450 center obligation');
ok(Wallet::balance($earner)===45000,'Center settlement never credits courier twice');
ok(!WalletEarnings::unsettled()->where('center_user_id',$owner)->exists(),'Funded center has no remaining obligation');
$other=user('rider','immediateother');loginAs($other);$controller->creditCourierEarnings(requestFor(['_request_key'=>(string)Str::uuid()]));ok(Wallet::balance($other)===0,'Courier cannot claim another courier earnings');
loginAs($buyer);try{$controller->creditCourierEarnings(requestFor(['_request_key'=>(string)Str::uuid()]));throw new RuntimeException('Buyer claimed courier earnings');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Only courier roles can credit completed-job earnings');}
echo 'PASS: '.($n-$begin)." immediate courier wallet credit checks.\n";
