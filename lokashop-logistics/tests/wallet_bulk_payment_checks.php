<?php
require __DIR__.'/bulk_trip_checks.php';
use Illuminate\Support\Facades\DB;
use App\Services\{Wallet,WalletEarnings};
$begin=$n;
$completed=DB::table('assignments')->where('rider_id',$driver)->where('kind','transfer')->where('status','completed')->pluck('id');
ok($completed->count()>0,'Fixture includes destination-confirmed driver jobs');
ok(DB::table('wallet_courier_payments')->whereIn('assignment_id',$completed)->count()===$completed->count(),'Each received bulk parcel queues one driver payment');
$incomplete=DB::table('assignments')->where('rider_id',$driver)->where('kind','transfer')->where('status','!=','completed')->pluck('id');
ok(!DB::table('wallet_courier_payments')->whereIn('assignment_id',$incomplete)->exists(),'Offers and unreceived transfers never create payable earnings');
$owed=(int)WalletEarnings::unsettled()->whereIn('assignment_id',$completed)->sum('amount_cents');
ok($owed>0&&Wallet::balance($driver)===$owed,'Driver gets earnings immediately while center funding is tracked separately');
Wallet::cashIn($company,$owed,'bulk-wallet-funding-0001');WalletEarnings::payPending($company);WalletEarnings::payPending($company);
ok(Wallet::balance($driver)===$owed,'Confirmed bulk jobs credit driver wallet once');
ok(Wallet::balance($company)===0,'Origin sorting center pays exact driver amount');
ok(DB::table('wallet_courier_payments')->whereIn('assignment_id',$completed)->where('status','paid')->count()===$completed->count(),'All funded bulk payments are marked paid');
echo 'PASS: '.($n-$begin)." bulk wallet payment checks.\n";
