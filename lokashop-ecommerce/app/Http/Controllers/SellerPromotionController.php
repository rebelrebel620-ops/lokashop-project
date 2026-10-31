<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Support;
class SellerPromotionController extends Controller
{
 public function index(Request $r){
  abort_unless(auth()->user()->role==='seller',403);$tab=$r->query('tab')==='deals'?'deals':'offers';
  $products=DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->orderBy('name')->get();
  $offers=DB::table('promotions as m')->leftJoin('products as p','p.id','=','m.product_id')->where('m.seller_id',auth()->id())->select('m.*','p.name as product_name','p.images')->orderByDesc('m.id')->paginate(12)->withQueryString();
  $deals=DB::table('bundle_deals')->where('seller_id',auth()->id())->orderByDesc('id')->paginate(12,['*'],'deal_page')->withQueryString();
  return view('seller-promotions',['links'=>app(CommonController::class)->links(),'tab'=>$tab,'products'=>$products,'offers'=>$offers,'deals'=>$deals]);
 }
 public function offer(Request $r){
  abort_unless(auth()->user()->role==='seller',403);
  $v=$r->validate(['id'=>'nullable|integer','product_id'=>'required|integer','type'=>'required|in:sale,flash','price'=>'required|numeric|decimal:0,2|min:1','quantity'=>'exclude_unless:type,flash|required|integer|min:1','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at|after:now']);
  foreach(['starts_at','ends_at'] as $field)$v[$field]=\Illuminate\Support\Carbon::parse($v[$field])->format('Y-m-d H:i:s');$id=$v['id']??null;unset($v['id']);$v['quantity']=$v['type']==='flash'?$v['quantity']:null;
  Support::once($r,'seller-promotion',function()use($v,$id){
   $p=DB::table('products')->where('id',$v['product_id'])->where('seller_id',auth()->id())->whereNull('deleted_at')->lockForUpdate()->first();abort_unless($p,403);
   if(!$p->active||!$p->approved||$p->draft)Support::fail('product_id','Choose an approved active listing, or update it in Products first.');
   if($id){$old=DB::table('promotions')->where('id',$id)->where('seller_id',auth()->id())->lockForUpdate()->first();abort_unless($old,403);if($old->used>0)Support::fail('promotion','End an already-used promotion and create a new offer instead.');}
   $current=round($p->price*(1-$p->discount_percent/100),2);if($v['price']>=$current)Support::fail('price','The offer must be below the product’s current discounted price of PHP '.number_format($current,2).'.');
   $minimum=DB::table('product_variations')->where('product_id',$p->id)->whereNull('deleted_at')->min('price_adjustment');if($minimum!==null&&$v['price']+$minimum<1)Support::fail('price','This price makes a variation cost less than PHP 1.00.');
   if($v['type']==='flash'&&$v['quantity']>$p->stock)Support::fail('quantity','Flash-sale quantity exceeds current product stock.');
   $other=DB::table('promotions')->where('product_id',$p->id)->where('type',$v['type']==='flash'?'sale':'flash')->where('active',1)->where('starts_at','<',$v['ends_at'])->where('ends_at','>',$v['starts_at']);
   if($id)$other->where('id','!=',$id);
   if(($v['type']==='flash'&&(clone $other)->where('price','<=',$v['price'])->exists())||($v['type']==='sale'&&(clone $other)->where('price','>=',$v['price'])->exists()))Support::fail('price','A flash price must be lower than any overlapping sale price.');
   if(DB::table('promotions')->where('product_id',$p->id)->where('type',$v['type'])->where('active',1)->where('starts_at','<',$v['ends_at'])->where('ends_at','>',$v['starts_at'])->when($id,fn($q)=>$q->where('id','!=',$id))->exists())Support::fail('starts_at','Another offer of this type overlaps this schedule. End it or choose other dates.');
   if($id)DB::table('promotions')->where('id',$id)->update($v+['updated_at'=>now()]);else $id=DB::table('promotions')->insertGetId($v+['seller_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
   return $id;
  });return $this->done($r,'Promotion scheduled.');
 }
 public function deal(Request $r){
  abort_unless(auth()->user()->role==='seller',403);
  $v=$r->validate(['type'=>'required|in:bundle,buy_x_get_y','product_ids'=>'required|array|min:1','product_ids.*'=>'required|integer|distinct','minimum_quantity'=>'required|integer|min:1','percent'=>'exclude_unless:type,bundle|required|numeric|gt:0|max:100','gift_product_id'=>'exclude_unless:type,buy_x_get_y|required|integer','gift_quantity'=>'exclude_unless:type,buy_x_get_y|required|integer|min:1','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at|after:now']);
  $ids=array_values(array_unique(array_map('intval',$v['product_ids'])));$gift=$v['type']==='buy_x_get_y';
  if($gift&&in_array((int)$v['gift_product_id'],$ids,true))Support::fail('gift_product_id','Choose a gift different from the qualifying products, so it does not count toward its own purchase requirement.');
  foreach(['starts_at','ends_at'] as $field)$v[$field]=\Illuminate\Support\Carbon::parse($v[$field])->format('Y-m-d H:i:s');
  Support::once($r,'bundle',function()use($v,$ids,$gift){
   $owned=$ids;if($gift)$owned[]=(int)$v['gift_product_id'];
   $products=DB::table('products')->where('seller_id',auth()->id())->whereIn('id',$owned)->whereNull('deleted_at')->where('active',1)->where('approved',1)->where('draft',0)->lockForUpdate()->get();
   if($products->count()!==count($owned))Support::fail('product_ids','Choose only approved active products from your store.');
   if($gift&&$products->firstWhere('id',(int)$v['gift_product_id'])->stock<$v['gift_quantity'])Support::fail('gift_quantity','Gift quantity exceeds its current stock.');
   $data=$v;$data['product_ids']=json_encode($ids);$data['percent']=$gift?0:$v['percent'];$data['gift_product_id']=$gift?$v['gift_product_id']:null;$data['gift_quantity']=$gift?$v['gift_quantity']:1;
   return DB::table('bundle_deals')->insertGetId($data+['seller_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
  });return $this->done($r,'Deal scheduled. Buyers must add the qualifying and gift products to their cart.');
 }
 public function endDeal(Request $r){abort_unless(auth()->user()->role==='seller',403);$id=$r->validate(['id'=>'required|integer|min:1'])['id'];$row=DB::table('bundle_deals')->where('id',$id)->where('seller_id',auth()->id())->first();abort_unless($row,404);DB::table('bundle_deals')->where('id',$id)->update(['active'=>0,'updated_at'=>now()]);return $this->done($r,'Deal ended.');}
 private function done(Request $r,string $message){return $r->expectsJson()?response()->json(['message'=>$message]):back()->with('success',$message);}
}
