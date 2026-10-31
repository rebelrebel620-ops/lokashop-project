<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ProductReviews
{
 public static function query(int $product){
  return DB::table('item_reviews as r')->join('order_items as i','i.id','=','r.order_item_id')
   ->where('i.product_id',$product)->whereBetween('r.stars',[1,5]);
 }
 public static function sold(int $product):int{
  return (int)DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')
   ->where('i.product_id',$product)->whereIn('o.status',['DELIVERED','COMPLETED'])
   ->whereNull('o.refunded_at')->sum('i.quantity');
 }
 public static function summary(int $product):array{
  $stats=self::query($product)->selectRaw('COUNT(*) as total, AVG(r.stars) as average')->first();
  $distribution=self::query($product)->selectRaw('r.stars, COUNT(*) as total')->groupBy('r.stars')->pluck('total','r.stars');
  return ['sold'=>self::sold($product),'count'=>(int)$stats->total,'average'=>(float)($stats->average??0),'distribution'=>$distribution];
 }
 public static function listing(int $product){
  return self::query($product)->leftJoin('users as b','b.id','=','r.buyer_id')
   ->select('r.id','r.stars','r.comment','r.photos','r.seller_response','r.created_at','b.name as reviewer_name')
   ->orderByDesc('r.created_at')->orderByDesc('r.id')->paginate(8,['*'],'review_page')->withQueryString()->fragment('customer-reviews');
 }
}
