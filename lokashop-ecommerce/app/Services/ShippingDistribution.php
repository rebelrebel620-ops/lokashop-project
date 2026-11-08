<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Schema};
class ShippingDistribution {
 public static function modern($order): bool {return (int)($order->shipping_split_version??1)===2;}
 public static function portions($fee): array {$pool=Wallet::cents($fee);$center=intdiv($pool*50+50,100);$driver=intdiv($pool*25,100);return ['pool'=>$pool,'center'=>$center,'driver'=>$driver,'rider'=>$pool-$center-$driver];}
 private static function equal(int $cents,array $ids): array {$ids=array_values(array_unique(array_map('intval',$ids)));sort($ids,SORT_NUMERIC);$out=[];$n=count($ids);if(!$n)return $out;foreach($ids as $i=>$id)$out[$id]=intdiv($cents,$n)+($i<$cents%$n?1:0);return $out;}
 public static function center($a): ?int {$id=($a->handling_center_id??null)?DB::table('sorting_centers')->where('id',$a->handling_center_id)->value('user_id'):null;return $id?(int)$id:((int)DB::table('users')->where('id',$a->rider_id)->value('company_id')?:null);}
 public static function finalize(int $orderId): void {
  DB::transaction(function()use($orderId){
   $o=DB::table('orders')->where('id',$orderId)->lockForUpdate()->first();if(!$o||!self::modern($o)||!in_array($o->status,['DELIVERED','COMPLETED'],true))return;
   if(DB::table('shipping_distributions')->where('order_id',$orderId)->whereNotNull('finalized_at')->exists())return;
   $p=DB::table('payments')->where('order_id',$orderId)->orderByDesc('id')->first();if(!$p||$p->status!=='paid')return;
   $jobs=DB::table('assignments')->whereIn('parcel_id',DB::table('parcels')->where('order_id',$orderId)->select('id'))->where('status','completed')->orderBy('id')->lockForUpdate()->get();
   $centers=[];$drivers=[];$riders=[];
   foreach($jobs as $a){$center=self::center($a);if($center&&DB::table('users')->where('id',$center)->where('role','sorting_center')->exists())$centers[]=$center;if($a->kind==='transfer')$drivers[$a->rider_id][]=$a;if($a->kind==='delivery')$riders[$a->rider_id][]=$a;}
   $parts=self::portions($o->shipping_fee);$paid=0;
   foreach(self::equal($parts['center'],$centers) as $user=>$amount){Wallet::creditEarnings($user,$amount,'shipping-share:center:'.$orderId.':'.$user,'shipping_income','50% center pool share for order #'.$orderId,$orderId);$paid+=$amount;}
   foreach(['driver'=>$drivers,'rider'=>$riders] as $role=>$participants){foreach(self::equal($parts[$role],array_keys($participants)) as $user=>$amount){$byJob=[];foreach($participants[$user] as $a)$byJob[$a->id]=$a;foreach(self::equal($amount,array_keys($byJob)) as $aid=>$jobAmount){$a=$byJob[$aid];$center=self::center($a);if(!$center)continue;
    Wallet::creditEarnings($user,$jobAmount,'courier-earn:'.$aid,'courier_earnings',ucfirst($role).' 25% pool share for order #'.$orderId,$orderId);
    DB::table('assignments')->where('id',$aid)->update(['earnings'=>$jobAmount/100]);
    DB::table('wallet_courier_payments')->updateOrInsert(['assignment_id'=>$aid],['center_user_id'=>$center,'courier_user_id'=>$user,'amount_cents'=>$jobAmount,'funding_source'=>'shipping_pool','status'=>'paid','paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);$paid+=$jobAmount;
   }}}
   DB::table('shipping_distributions')->updateOrInsert(['order_id'=>$orderId],['pool_cents'=>$parts['pool'],'center_cents'=>$parts['center'],'driver_cents'=>$parts['driver'],'rider_cents'=>$parts['rider'],'paid_cents'=>$paid,'unallocated_cents'=>$parts['pool']-$paid,'finalized_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  },3);
 }
}
