<?php
namespace App\Services;
class VoucherDisplay
{
 public static function kind($voucher):string{return $voucher->seller_id?'store':($voucher->type==='shipping'?'shipping':'lokashop');}
 public static function label($voucher):string{return ['store'=>'Store voucher','shipping'=>'Shipping voucher','lokashop'=>'LokaShop'][self::kind($voucher)];}
 public static function offer($voucher):string{
  $value=$voucher->value??$voucher->discount_percent??0;
  return $voucher->type==='percent'?rtrim(rtrim(number_format($value,2),'0'),'.').'% OFF':'₱'.number_format($value,2).' OFF'.($voucher->type==='shipping'?' SHIPPING':'');
 }
 public static function state($v,int $used=0):string{
  if(!$v->active)return 'Ended';
  if($v->expires_at&&now()->gte($v->expires_at))return 'Expired';
  if($v->starts_at&&now()->lt($v->starts_at))return 'Coming soon';
  if($v->usage_limit!==null&&$v->used_count>=$v->usage_limit)return 'Fully redeemed';
  if($used>=($v->per_buyer_limit??1))return 'Usage limit reached';
  return 'Available';
 }
}
