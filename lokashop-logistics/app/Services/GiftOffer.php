<?php
namespace App\Services;
class GiftOffer
{
 public static function discount(array $items,int $product,int $limit):float{
  $remaining=max(0,$limit);$discount=0;
  foreach($items as $item){if($remaining===0)break;if((int)$item['p']->id!==$product)continue;
   $free=min((int)$item['quantity'],$remaining);$discount+=$free*$item['price'];$remaining-=$free;
  }
  return Support::money($discount);
 }
}
