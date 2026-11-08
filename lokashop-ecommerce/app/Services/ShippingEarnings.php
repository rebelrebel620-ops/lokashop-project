<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ShippingEarnings {
 public static function amount(int $orderId,string $kind):float {
  $o=DB::table('orders')->find($orderId);
  if(ShippingDistribution::modern($o)){return $kind==='delivery'?ShippingDistribution::portions($o->shipping_fee)['rider']/100:0;}
  // Preserve amounts on shipments already credited before the new split.
  return $kind==='delivery'?Support::money(max(0,$o->shipping_fee-$o->shipping_commission)):($kind==='transfer'?50:0);
 }
}
