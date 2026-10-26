<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
class Commerce {
 public static function catalog($q){return $q->where('products.active',1)->where('products.approved',1)->where('products.draft',0)->whereNull('products.deleted_at')->whereIn('products.seller_id',DB::table('users')->where('role','seller')->where('active',1)->where('account_status','active')->where('approval_status','approved')->select('id'));}
 public static function checkout(Request $r): array {
  $v=$r->validate(['address_id'=>'required|integer','payment_method'=>'required|in:cod','voucher'=>'nullable|string|max:60','platform_voucher'=>'nullable|string|max:60','shipping_voucher'=>'nullable|string|max:60','store_vouchers'=>'nullable|array','store_vouchers.*'=>'string|max:60','cart_ids'=>'nullable|array|min:1','cart_ids.*'=>'integer','note'=>'nullable|string|max:200']);
  if(!empty($v['store_vouchers']))Support::fail('voucher','Choose only one store voucher using the store voucher field.');
  $checkoutKey=$r->header('Idempotency-Key')??$r->input('_request_key');return Support::once($r,'checkout',function()use($v,$checkoutKey){
   $address=DB::table('addresses')->where('id',$v['address_id'])->where('user_id',auth()->id())->first();abort_unless($address,403);
   $q=DB::table('cart_items')->where('buyer_id',auth()->id());if(isset($v['cart_ids']))$q->whereIn('id',$v['cart_ids']);
   $rows=$q->orderBy('product_id')->orderBy('variation_id')->lockForUpdate()->get();if($rows->isEmpty())Support::fail('cart','Select at least one item.');
   $products=DB::table('products')->whereIn('id',$rows->pluck('product_id')->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
   $variants=DB::table('product_variations')->whereIn('id',$rows->pluck('variation_id')->filter()->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
   $need=[];$vn=[];foreach($rows as $row){$need[$row->product_id]=($need[$row->product_id]??0)+$row->quantity;if($row->variation_id)$vn[$row->variation_id]=($vn[$row->variation_id]??0)+$row->quantity;}
   foreach($need as $id=>$qty){$p=$products[$id]??null;if(!$p||!$p->active||!$p->approved||$p->draft||$p->deleted_at)Support::fail('cart','A product is unavailable.');
    $seller=DB::table('users')->find($p->seller_id);$shop=DB::table('seller_businesses')->where('user_id',$p->seller_id)->first();
    if(!$seller||!$seller->active||$seller->approval_status!=='approved'||($shop&&$shop->vacation))Support::fail('cart','The seller is unavailable or on vacation.');
    if($qty>$p->stock)Support::fail('stock',"Not enough stock for {$p->name}.");
    if($p->purchase_limit){$bought=DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.buyer_id',auth()->id())->where('i.product_id',$id)->whereNotIn('o.status',['CANCELLED','REFUNDED'])->sum('i.quantity');if($bought+$qty>$p->purchase_limit)Support::fail('quantity','Purchase limit exceeded.');}
   }
   foreach($vn as $id=>$qty)if(!isset($variants[$id])||$variants[$id]->deleted_at||$qty>$variants[$id]->stock)Support::fail('stock','Variant stock is insufficient.');
   $groups=[];
   foreach($rows as $row){$p=$products[$row->product_id];$var=$row->variation_id?$variants[$row->variation_id]:null;
    if($var&&$var->product_id!=$p->id)Support::fail('variation_id','Variation does not belong to this product.');
    if(!$var&&DB::table('product_variations')->where('product_id',$p->id)->whereNull('deleted_at')->exists())Support::fail('variation_id','Select a variation.');
    $regular=Support::money(($p->price+($var->price_adjustment??0))*(1-$p->discount_percent/100));
    $sale=DB::table('promotions')->where('product_id',$p->id)->where('active',1)->where('starts_at','<=',now())->where('ends_at','>',now())->orderBy('id')->lockForUpdate()->get();
    $scheduled=$sale->firstWhere('type','sale');if($scheduled)$regular=Support::money($scheduled->price+($var->price_adjustment??0));
    if($regular<1)Support::fail('price','Product effective price must be at least PHP 1.00.');
    $flash=$sale->firstWhere('type','flash');$flashQty=$flash?min($row->quantity,max(0,$flash->quantity-$flash->used)):0;
    $parts=[];if($flashQty){$parts[]=[$flashQty,Support::money($flash->price+($var->price_adjustment??0))];DB::table('promotions')->where('id',$flash->id)->increment('used',$flashQty);}
    if($row->quantity>$flashQty)$parts[]=[$row->quantity-$flashQty,$regular];
    foreach($parts as [$qty,$price]){$groups[$p->seller_id]['items'][]=['p'=>$p,'v'=>$var,'quantity'=>$qty,'price'=>$price];$groups[$p->seller_id]['subtotal']=Support::money(($groups[$p->seller_id]['subtotal']??0)+$qty*$price);$groups[$p->seller_id]['weight']=($groups[$p->seller_id]['weight']??0)+$qty*$p->weight;}
   }
   $totalMerch=0;$totalShip=0;$voucherUses=[];$legacySeller=null;if(!empty($v['voucher'])){$legacy=DB::table('vouchers')->whereRaw('UPPER(code) = ?',[strtoupper(trim($v['voucher']))])->first();if(!$legacy||!$legacy->seller_id||!isset($groups[$legacy->seller_id]))Support::fail('voucher','Store voucher is invalid or not applicable to these items.');$legacySeller=$legacy->seller_id;}
   foreach($groups as $sid=>&$g){
    $origin=DB::table('addresses')->where('user_id',$sid)->orderByDesc('is_default')->value('province')??'';
    $serving=Municipalities::centerFor($address);if(!$serving)Support::fail('address_id','No approved sorting center serves your municipality yet.');$rate=DB::table('shipping_rates')->where('center_id',$serving->id)->where(fn($q)=>$q->where('origin_province',$origin)->orWhere('origin_province',''))->orderByRaw("CASE WHEN origin_province = ? THEN 0 ELSE 1 END",[$origin])->where('city',$address->city)->where('province',$address->province)->where('active',1)->orderBy('id')->first();
    // Flat PHP 50 per seller shipment only when no active configured rate matches.
    $g['shipping']=Support::money($rate ? $rate->base_fee+max(0,ceil($g['weight'])-1)*$rate->extra_kg_fee : 50);
    $bundle=0;foreach(DB::table('bundle_deals')->where('seller_id',$sid)->where('active',1)->where('starts_at','<=',now())->where('ends_at','>',now())->get() as $deal){$eligible=json_decode($deal->product_ids,true);$quantity=0;$base=0;$gift=0;foreach($g['items'] as $item){if(in_array($item['p']->id,$eligible)){ $quantity+=$item['quantity'];$base+=$item['quantity']*$item['price'];}if($deal->gift_product_id==$item['p']->id)$gift+=min($item['quantity'],$deal->gift_quantity)*$item['price'];}if($quantity>=$deal->minimum_quantity)$bundle=max($bundle,$deal->type==='bundle'?$base*$deal->percent/100:$gift);}$bundle=Support::money(min($bundle,$g['subtotal']));
    $code=$v['store_vouchers'][$sid]??($sid==$legacySeller?($v['voucher']??''):'');$g['store']=$bundle;$g['voucher']=null;
    if($code){$voucher=self::voucher($code,$sid,$g['subtotal']-$bundle);$g['store']=$bundle+self::discount($voucher,$g['subtotal']-$bundle);$g['voucher']=$voucher;$voucherUses[$voucher->id]=$voucher;}
    $g['bundle']=$bundle;$g['net']=Support::money($g['subtotal']-$g['store']);$totalMerch+=$g['net'];$totalShip+=$g['shipping'];
   }unset($g);
   $platform=!empty($v['platform_voucher'])?self::voucher($v['platform_voucher'],null,$totalMerch,'platform'):null;
   $shipping=!empty($v['shipping_voucher'])?self::voucher($v['shipping_voucher'],null,$totalMerch,'shipping'):null;
   $pd=$platform?self::discount($platform,$totalMerch):0;$sd=$shipping?self::discount($shipping,$totalShip):0;
   $pa=self::allocate($pd,array_map(fn($g)=>$g['net'],$groups));$sa=self::allocate($sd,array_map(fn($g)=>$g['shipping'],$groups));
   if($platform)$voucherUses[$platform->id]=$platform;if($shipping)$voucherUses[$shipping->id]=$shipping;
   $productGroups=[];$productPA=[];$productSA=[];
   foreach($groups as $sid=>$store){$parts=[];foreach($store['items'] as $item){$pid=$item['p']->id;$parts[$pid]['items'][]=$item;$parts[$pid]['subtotal']=Support::money(($parts[$pid]['subtotal']??0)+$item['quantity']*$item['price']);}
    $weights=array_map(fn($part)=>$part['subtotal'],$parts);$storeDiscount=self::allocate($store['store'],$weights);$bundleDiscount=self::allocate($store['bundle'],$weights);$shippingFees=self::allocate($store['shipping'],$weights);$platformParts=self::allocate($pa[$sid]??0,$weights);$shippingParts=self::allocate($sa[$sid]??0,$weights);$commissions=self::allocate(Support::money($store['net']*.10),$weights);
    foreach($parts as $pid=>$part){$key=$sid.':'.$pid;$part+=['seller_id'=>$sid,'store'=>$storeDiscount[$pid],'bundle'=>$bundleDiscount[$pid],'shipping'=>$shippingFees[$pid],'voucher'=>$store['voucher'],'net'=>Support::money($part['subtotal']-$storeDiscount[$pid]),'commission'=>$commissions[$pid]];$productGroups[$key]=$part;$productPA[$key]=$platformParts[$pid];$productSA[$key]=$shippingParts[$pid];}
   }
   $groups=$productGroups;$pa=$productPA;$sa=$productSA;
   $created=[];
   foreach($groups as $groupKey=>$g){$sid=$g['seller_id'];$total=Support::money($g['net']+$g['shipping']-($pa[$groupKey]??0)-($sa[$groupKey]??0));
    $oid=DB::table('orders')->insertGetId(['checkout_key'=>$checkoutKey,'buyer_id'=>auth()->id(),'seller_id'=>$sid,'address_id'=>$address->id,'address_snapshot'=>json_encode($address),'note'=>$v['note']??null,'status'=>'PLACED','total'=>$total,'discount'=>Support::money($g['store']+($pa[$groupKey]??0)+($sa[$groupKey]??0)),'merchandise_total'=>$g['subtotal'],'shipping_fee'=>$g['shipping'],'shipping_commission'=>Support::money($g['shipping']*.20),'store_discount'=>$g['store'],'platform_discount'=>$pa[$groupKey]??0,'shipping_discount'=>$sa[$groupKey]??0,'commission'=>$g['commission'],'voucher_id'=>$g['voucher']->id??null,'created_at'=>now(),'updated_at'=>now()]);$created[]=$oid;
    foreach($g['items'] as $x){$p=$x['p'];$var=$x['v'];$images=json_decode($p->images??'[]',true);
     DB::table('order_items')->insert(['order_id'=>$oid,'product_id'=>$p->id,'variation_id'=>$var->id??null,'name'=>$p->name.' '.($var->name??''),'quantity'=>$x['quantity'],'unit_price'=>$x['price'],'image'=>$var->image??($images[0]??null),'created_at'=>now(),'updated_at'=>now()]);}
    $pvids=[];if($g['voucher'])$pvids[$g['voucher']->id]=$g['store']-$g['bundle'];if($platform)$pvids[$platform->id]=$pa[$groupKey];if($shipping)$pvids[$shipping->id]=$sa[$groupKey];
    foreach($pvids as $vid=>$amt)DB::table('order_vouchers')->insert(['order_id'=>$oid,'voucher_id'=>$vid,'amount'=>$amt]);
    DB::table('payments')->insert(['order_id'=>$oid,'method'=>$v['payment_method'],'status'=>$v['payment_method']==='cod'?'due_on_delivery':'awaiting_manual_confirmation','amount'=>$total,'created_at'=>now(),'updated_at'=>now()]);
    $pid=DB::table('parcels')->insertGetId(['order_id'=>$oid,'tracking_code'=>'LKS-'.strtoupper(Str::random(12)),'status'=>'PLACED','created_at'=>now(),'updated_at'=>now()]);
    DB::table('status_history')->insert(['parcel_id'=>$pid,'to_status'=>'PLACED','actor_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);Support::notify([$sid,auth()->id()],"Order #{$oid} placed.");
   }
   foreach($need as $id=>$qty){DB::table('products')->where('id',$id)->decrement('stock',$qty);self::stockAlert('products',$id);}
   foreach($vn as $id=>$qty){DB::table('product_variations')->where('id',$id)->decrement('stock',$qty);self::stockAlert('product_variations',$id);}
   foreach($voucherUses as $vv){DB::table('vouchers')->where('id',$vv->id)->increment('used_count');DB::table('voucher_redemptions')->insert(['buyer_id'=>auth()->id(),'voucher_id'=>$vv->id,'checkout_key'=>$checkoutKey,'created_at'=>now(),'updated_at'=>now()]);}
   DB::table('cart_items')->whereIn('id',$rows->pluck('id'))->where('buyer_id',auth()->id())->delete();return $created;
  });
 }
 public static function allocate(float $amount,array $weights): array {
  $total=array_sum($weights);$out=[];foreach($weights as $id=>$w)$out[$id]=$total>0?round($amount*$w/$total,2,PHP_ROUND_HALF_UP):0;
  if($out){$largest=array_keys($weights,max($weights))[0];$out[$largest]=Support::money($out[$largest]+$amount-array_sum($out));}return $out;
 }
 private static function voucher(string $code,?int $seller,float $subtotal,?string $kind=null){
  $x=DB::table('vouchers')->whereRaw('UPPER(code) = ?',[strtoupper(trim($code))])->lockForUpdate()->first();
  if(!$x||!$x->active||($x->starts_at&&now()->lt($x->starts_at))||($x->expires_at&&now()->gte($x->expires_at)))Support::fail('voucher','Voucher is invalid, not started or expired.');
  if($seller!==null&&$x->seller_id!=$seller)Support::fail('voucher','Use the voucher only for its store.');
  if($seller===null&&($x->seller_id!==null||($kind==='shipping'&&$x->type!=='shipping')||($kind==='platform'&&$x->type==='shipping')))Support::fail('voucher','Wrong voucher type.');
  if($x->minimum_spend>$subtotal)Support::fail('voucher','Minimum spend is not met.');
  if($x->usage_limit!==null&&$x->used_count>=$x->usage_limit)Support::fail('voucher','Voucher usage limit reached.');
  $used=DB::table('voucher_redemptions')->where('buyer_id',auth()->id())->where('voucher_id',$x->id)->count();
  if($used>=$x->per_buyer_limit)Support::fail('voucher','Your voucher usage limit is reached.');
  if($x->claimable)DB::table('voucher_claims')->insertOrIgnore(['voucher_id'=>$x->id,'buyer_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
  return $x;
 }
 private static function discount($v,float $base):float {$d=$v->type==='percent'?$base*($v->value??$v->discount_percent)/100:($v->value??0);if($v->cap!==null)$d=min($d,$v->cap);return Support::money(max(0,min($d,$base)));}
 public static function stockAlert(string $table,int $id):void {
  $x=DB::table($table)->find($id);$low=$x->stock<=5;
  if($low&&!$x->low_stock_notified){$sid=$table==='products'?$x->seller_id:DB::table('products')->where('id',$x->product_id)->value('seller_id');Support::notify([$sid],"Low stock: {$x->name} ({$x->stock} left).");}
  DB::table($table)->where('id',$id)->update(['low_stock_notified'=>$low]);
 }
 public static function restore($o):void {
  if($o->stock_restored_at)return;
  foreach(DB::table('order_items')->where('order_id',$o->id)->get() as $i){DB::table('products')->where('id',$i->product_id)->increment('stock',$i->quantity);self::stockAlert('products',$i->product_id);if($i->variation_id){DB::table('product_variations')->where('id',$i->variation_id)->increment('stock',$i->quantity);self::stockAlert('product_variations',$i->variation_id);}}
  DB::table('orders')->where('id',$o->id)->update(['stock_restored_at'=>now()]);
 }
 public static function cancel(int $id,string $reason,?int $actor=null):void {
  DB::transaction(function()use($id,$reason,$actor){$o=DB::table('orders')->where('id',$id)->lockForUpdate()->first();abort_unless($o,404);$u=$actor?DB::table('users')->find($actor):null;
   if($u)abort_unless($u->id==$o->buyer_id||$u->id==$o->seller_id,403);
   if($o->status==='CANCELLED')return;
   $allowed=$u&&$u->role==='seller'?['PLACED','CONFIRMED','PREPARING','READY_FOR_PICKUP']:['PLACED','CONFIRMED','PREPARING'];
   if(!in_array($o->status,$allowed,true))Support::fail('order','This order can no longer be cancelled.');
   self::restore($o);self::refund($o);
   DB::table('orders')->where('id',$id)->update(['status'=>'CANCELLED','cancelled_at'=>now(),'updated_at'=>now()]);DB::table('parcels')->where('order_id',$id)->update(['status'=>'CANCELLED','updated_at'=>now()]);
   DB::table('assignments')->whereIn('parcel_id',DB::table('parcels')->where('order_id',$id)->select('id'))->whereIn('status',['requested','offered','accepted'])->update(['status'=>'cancelled','updated_at'=>now()]);
   foreach(DB::table('order_vouchers')->where('order_id',$id)->get() as $ov){$voucher=DB::table('vouchers')->where('id',$ov->voucher_id)->lockForUpdate()->first();if($voucher&&$o->checkout_key){$remaining=DB::table('order_vouchers as ov')->join('orders as orders_v','orders_v.id','=','ov.order_id')->where('orders_v.checkout_key',$o->checkout_key)->where('ov.voucher_id',$voucher->id)->where('orders_v.status','!=','CANCELLED')->exists();if(!$remaining){$removed=DB::table('voucher_redemptions')->where('buyer_id',$o->buyer_id)->where('voucher_id',$voucher->id)->where('checkout_key',$o->checkout_key)->delete();if($removed&&$voucher->used_count>0)DB::table('vouchers')->where('id',$voucher->id)->decrement('used_count');}}}
   Support::audit('cancel','order',$id,$o,['status'=>'CANCELLED'],$reason);Support::notify([$o->buyer_id,$o->seller_id],"Order #{$id} cancelled: {$reason}");
  });
 }
 public static function refund($o):void {
  $p=DB::table('payments')->where('order_id',$o->id)->orderByDesc('id')->first();
  if($p&&$p->status==='paid')DB::table('refunds')->updateOrInsert(['order_id'=>$o->id],['amount'=>$o->total,'method'=>$p->method,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
 }
 public static function available(int $seller):float {
  $earned=DB::table('orders')->where('seller_id',$seller)->where('status','COMPLETED')->whereNull('refunded_at')->selectRaw('COALESCE(SUM(merchandise_total-store_discount-commission),0) n')->value('n');
  $paid=DB::table('payouts')->where('seller_id',$seller)->whereIn('status',['pending','paid'])->sum('amount');return Support::money($earned-$paid);
 }
}
