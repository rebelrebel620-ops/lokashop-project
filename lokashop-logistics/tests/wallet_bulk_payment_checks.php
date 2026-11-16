<?php
require __DIR__.'/bulk_trip_checks.php';
use Illuminate\Support\Facades\DB;
use App\Services\{Wallet,WalletEarnings,ShippingDistribution};
$begin=$n;
$completed=DB::table('assignments')->where('rider_id',$driver)->where('kind','transfer')->where('status','completed')->pluck('id');
ok($completed->count()>0,'Fixture includes destination-confirmed driver jobs');
ok(!DB::table('wallet_courier_payments')->whereIn('assignment_id',$completed)->exists(),'Modern bulk transfers wait for delivered shipping-pool allocation');
$incomplete=DB::table('assignments')->where('rider_id',$driver)->where('kind','transfer')->where('status','!=','completed')->pluck('id');
ok(!DB::table('wallet_courier_payments')->whereIn('assignment_id',$incomplete)->exists(),'Unreceived transfers never create payable earnings');
$orders=DB::table('parcels')->whereIn('id',DB::table('assignments')->whereIn('id',$completed)->pluck('parcel_id'))->pluck('order_id');
$before=Wallet::balance($driver);$centerBefore=Wallet::balance($company);$expected=0;
foreach($orders as $order){DB::table('orders')->where('id',$order)->update(['status'=>'DELIVERED']);DB::table('payments')->updateOrInsert(['order_id'=>$order],['method'=>'cod','status'=>'paid','amount'=>150,'created_at'=>now(),'updated_at'=>now()]);$expected+=ShippingDistribution::portions(DB::table('orders')->where('id',$order)->value('shipping_fee'))['driver'];ShippingDistribution::finalize($order);}
ok(Wallet::balance($driver)===$before+$expected,'Delivered bulk parcels allocate exactly one 25 percent driver share each');
foreach($orders as $order)ShippingDistribution::finalize($order);
WalletEarnings::payPending($company);
ok(Wallet::balance($driver)===$before+$expected,'Pool allocation and legacy catch-up cannot double-pay');
ok(DB::table('wallet_courier_payments')->whereIn('assignment_id',$completed)->where('funding_source','shipping_pool')->where('status','paid')->count()===$completed->count(),'Completed bulk shares record paid shipping-pool entries');
ok(!WalletEarnings::unsettled()->whereIn('assignment_id',$completed)->exists(),'Shipping pool never creates center funding debt');
echo 'PASS: '.($n-$begin)." bulk wallet payment checks.\n";
