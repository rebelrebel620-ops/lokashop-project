<?php
require __DIR__.'/bulk_trip_checks.php';
use Illuminate\Support\Facades\DB;
use App\Services\{Wallet,ShippingDistribution};
$beforeChecks=$n;
$jobs=DB::table('assignments')->where('trip_id',$trip)->where('kind','transfer')->get();ok($jobs->count()===6&&$jobs->every(fn($a)=>(float)$a->earnings===0.0),'Bulk driver has no fixed PHP 50 earning per parcel before final delivery');ok(Wallet::balance($driver)===0,'Confirmed bulk receipt waits for final route allocation');
$item=DB::table('driver_trip_items')->where('trip_id',$trip)->first();$parcel=DB::table('parcels')->find($item->parcel_id);$order=DB::table('orders')->find($parcel->order_id);$dest=$parcel->center_id;$owner=DB::table('sorting_centers')->where('id',$dest)->value('user_id');$finalRider=user('rider','splitbulkfinalrider');DB::table('users')->where('id',$finalRider)->update(['company_id'=>$owner]);
DB::table('orders')->where('id',$order->id)->update(['status'=>'DELIVERED']);DB::table('payments')->insert(['order_id'=>$order->id,'method'=>'cod','status'=>'paid','amount'=>150,'created_at'=>now(),'updated_at'=>now()]);DB::table('assignments')->insert(['parcel_id'=>$parcel->id,'handling_center_id'=>$dest,'rider_id'=>$finalRider,'kind'=>'delivery','status'=>'completed','earnings'=>12.5,'created_at'=>now(),'updated_at'=>now()]);ShippingDistribution::finalize($order->id);ShippingDistribution::finalize($order->id);
ok(Wallet::balance($driver)===1250&&Wallet::balance($finalRider)===1250,'Delivered bulk parcel pays 25% driver and 25% final rider once');
$d=DB::table('shipping_distributions')->where('order_id',$order->id)->first();ok($d->pool_cents===5000&&$d->paid_cents===5000,'Bulk parcel payout stays within its PHP 50 shipping pool');
ok(DB::table('wallet_courier_payments')->whereIn('assignment_id',$jobs->pluck('id'))->count()===1,'Other un-delivered bulk parcels do not receive early driver payouts');
echo 'PASS: '.($n-$beforeChecks)." new bulk shipping split checks.\n";
