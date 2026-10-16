<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class Catalog {
 public static function query(Request $r){
  $v=$r->validate(['q'=>'nullable|string|max:180','main_category'=>['nullable','string',\Illuminate\Validation\Rule::in(config('registration.categories'))],'category'=>'nullable|integer','seller'=>'nullable|integer','store_category'=>'nullable|integer','min_price'=>'nullable|numeric|min:0','max_price'=>'nullable|numeric|gte:min_price','sort'=>'nullable|in:newest,price_asc,price_desc,name']);
  $q=Commerce::catalog(DB::table('products'))->join('categories','categories.id','=','products.category_id')->select('products.*','categories.name as category');
  if($r->filled('main_category'))$q->where('categories.main_category',$v['main_category']);
  if($r->filled('q'))$q->where('products.name','like','%'.$v['q'].'%');if($r->filled('category'))$q->where('category_id',$v['category']);if($r->filled('seller'))$q->where('products.seller_id',$v['seller']);
  if($r->filled('store_category'))$q->whereIn('products.id',DB::table('product_store_categories as pc')->join('store_categories as sc','sc.id','=','pc.category_id')->where('pc.category_id',$v['store_category'])->where('sc.visible',1)->select('pc.product_id'));
  if($r->filled('min_price'))$q->whereRaw('products.price * (1-products.discount_percent/100) >= ?',[$v['min_price']]);if($r->filled('max_price'))$q->whereRaw('products.price * (1-products.discount_percent/100) <= ?',[$v['max_price']]);
  match($v['sort']??'newest'){'price_asc'=>$q->orderByRaw('products.price * (1-products.discount_percent/100) asc'),'price_desc'=>$q->orderByRaw('products.price * (1-products.discount_percent/100) desc'),'name'=>$q->orderBy('products.name'),default=>$q->orderByDesc('products.id')};return $q;
 }
 public static function price($p,$variant=null):float {$promos=DB::table('promotions')->where('product_id',$p->id)->where('active',1)->where('starts_at','<=',now())->where('ends_at','>',now())->get();$flash=$promos->first(fn($x)=>$x->type==='flash'&&$x->used<$x->quantity);$sale=$promos->firstWhere('type','sale');$price=$flash->price??$sale->price??null;return Support::money($price!==null?$price+($variant->price_adjustment??0):($p->price+($variant->price_adjustment??0))*(1-$p->discount_percent/100));}
}
