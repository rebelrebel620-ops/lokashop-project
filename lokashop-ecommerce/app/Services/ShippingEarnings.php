<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ShippingEarnings {
 // Seller pickup is free. Only a completed delivery earns 80% of shipping.
 public static function amount(int $orderId,string $kind):float {
  $o=DB::table('orders')->find($orderId);
  $pool=Support::money(max(0,$o->shipping_fee-$o->shipping_commission));
  return $kind==='delivery'?$pool:0;
 }
}
