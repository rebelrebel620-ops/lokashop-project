<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class Catalog {
 public static function query(Request $r){
  $v=$r->validate(['q'=>'nullable|string|max:180','main_category'=>['nullable','string',\Illuminate\Validation\Rule::in(config('registration.categories'))],'category'=>'nullable|integer','seller'=>'nullable|integer','store_category'=>'nullable|integer','min_price'=>'nullable|numeric|min:0','max_price'=>'nullable|numeric|gte:min_price','sort'=>'nullable|in:newest,price_asc,price_desc,name']);
  if($r->filled('main_category')&&$r->filled('category')&&!DB::table('categories')->where('id',$v['category'])->where('main_category',$v['main_category'])->exists())Support::fail('category','Choose a subcategory within the selected main category.');
  $q=Commerce::catalog(DB::table('products'))->join('categories','categories.id','=','products.category_id')->select('products.*','categories.name as category')->selectRaw(self::priceSql().' as catalog_effective_price');
  if($r->filled('main_category'))$q->where('categories.main_category',$v['main_category']);
  if($r->filled('q'))$q->where('products.name','like','%'.$v['q'].'%');if($r->filled('category'))$q->where('category_id',$v['category']);if($r->filled('seller'))$q->where('products.seller_id',$v['seller']);
  if($r->filled('store_category'))$q->whereIn('products.id',DB::table('product_store_categories as pc')->join('store_categories as sc','sc.id','=','pc.category_id')->where('pc.category_id',$v['store_category'])->where('sc.visible',1)->select('pc.product_id'));
  $priceSql=self::priceSql();
  if($r->filled('min_price'))$q->whereRaw($priceSql.' >= ?',[$v['min_price']]);if($r->filled('max_price'))$q->whereRaw($priceSql.' <= ?',[$v['max_price']]);
  match($v['sort']??'newest'){'price_asc'=>$q->orderByRaw($priceSql.' asc'),'price_desc'=>$q->orderByRaw($priceSql.' desc'),'name'=>$q->orderBy('products.name'),default=>$q->orderByDesc('products.id')};return $q;
 }
 // The catalog filters and display use the same flash > sale > regular precedence.
 public static function priceSql():string {
  return "ROUND(COALESCE((SELECT promo.price FROM promotions promo WHERE promo.product_id = products.id AND promo.active = 1 AND promo.starts_at <= CURRENT_TIMESTAMP AND promo.ends_at > CURRENT_TIMESTAMP AND promo.type = 'flash' AND promo.used < promo.quantity ORDER BY promo.id LIMIT 1), (SELECT promo.price FROM promotions promo WHERE promo.product_id = products.id AND promo.active = 1 AND promo.starts_at <= CURRENT_TIMESTAMP AND promo.ends_at > CURRENT_TIMESTAMP AND promo.type = 'sale' ORDER BY promo.id LIMIT 1), products.price * (1-products.discount_percent/100)), 2)";
 }
 public static function validateVariationPrices($product):void {
  $variants=DB::table('product_variations')->where('product_id',$product->id)->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
  foreach($variants as $variant)self::validateAdjustment($product,(float)$variant->price_adjustment);
 }
 public static function validateAdjustment($product,float $adjustment):void {
  $base=(float)$product->price+$adjustment;
  if($base>1000000||Support::money($base*(1-$product->discount_percent/100))<1)Support::fail('price','This price or discount would make a variation invalid. Adjust its price adjustment first.');
  foreach(DB::table('promotions')->where('product_id',$product->id)->where('active',1)->where('ends_at','>',now())->orderBy('id')->lockForUpdate()->get() as $promotion){
   $price=Support::money($promotion->price+$adjustment);
   if($price<1||$price>1000000)Support::fail('price','A variation conflicts with an active or scheduled promotion price. Adjust the variation or end the promotion first.');
  }
 }
 public static function price($p,$variant=null):float {if($variant===null&&isset($p->catalog_effective_price))return Support::money($p->catalog_effective_price);$promos=DB::table('promotions')->where('product_id',$p->id)->where('active',1)->where('starts_at','<=',now())->where('ends_at','>',now())->orderBy('id')->get();$flash=$promos->first(fn($x)=>$x->type==='flash'&&$x->used<$x->quantity);$sale=$promos->firstWhere('type','sale');$price=$flash->price??$sale->price??null;return Support::money($price!==null?$price+($variant->price_adjustment??0):($p->price+($variant->price_adjustment??0))*(1-$p->discount_percent/100));}
}
