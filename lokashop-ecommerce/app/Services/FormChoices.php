<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class FormChoices {
 public static function for(string $field,string $action):?array {
  if(!auth()->check())return null;$u=auth()->user();$q=null;$label='name';$key='id';
  switch($field){
   case 'category_id':$q=$u->role==='seller'?\App\Services\ProductCategories::seller($u->id):DB::table('categories')->whereNotNull('category_code');break;
   case 'parent_id':$q=DB::table('store_categories')->where('seller_id',$u->id)->whereNull('parent_id');break;
   case 'product_id':case 'gift_product_id':$q=DB::table('products')->whereNull('deleted_at');if($u->role==='seller')$q->where('seller_id',$u->id);else $q->where('approved',1)->where('active',1);break;
   case 'seller_id':$q=DB::table('users')->where('role','seller')->where('active',1);break;
   case 'company_id':$q=DB::table('users')->where('role','sorting_center')->where('active',1)->where('account_status','active')->where('approval_status','approved');break;
   case 'center_id':$q=DB::table('sorting_centers');break;
   case 'destination_center_id':$q=DB::table('sorting_centers')->where('user_id','!=',$u->id);break;
   case 'rider_id':$q=DB::table('users')->where('role','rider')->where('active',1);if($action==='/ops/legacy')$q->whereNull('company_id');elseif($u->role==='sorting_center')$q->where('company_id',$u->id);break;
   case 'voucher_id':$q=DB::table('vouchers')->where('active',1);$label='code';break;
   case 'order_id':$q=DB::table('orders');if($u->role==='buyer')$q->where('buyer_id',$u->id);elseif($u->role==='seller')$q->where('seller_id',$u->id);$label='status';break;
   case 'order_item_id':$q=DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.buyer_id',$u->id)->select('i.*');break;
   case 'parcel_id':$q=DB::table('parcels');$label='tracking_code';if($u->role==='sorting_center')$q->where('center_id',DB::table('sorting_centers')->where('user_id',$u->id)->value('id'));elseif($u->role==='seller')$q->whereIn('order_id',DB::table('orders')->where('seller_id',$u->id)->select('id'));break;
   case 'payment_id':$q=DB::table('payments')->where('method','manual');$label='reference';break;
   case 'id':
    if(str_contains($action,'address')){$q=DB::table('addresses')->where('user_id',$u->id);$label='street';}
    elseif(str_contains($action,'category'))$q=DB::table('store_categories')->where('seller_id',$u->id);
    elseif(str_contains($action,'promotion')){$q=DB::table('promotions')->where('seller_id',$u->id);$label='type';}
    elseif(str_contains($action,'voucher')){$q=DB::table('vouchers');$u->role==='admin'?$q->whereNull('seller_id'):$q->where('seller_id',$u->id);$label='code';}
    elseif(str_contains($action,'variant')){$q=DB::table('product_variations')->whereIn('product_id',DB::table('products')->where('seller_id',$u->id)->select('id'));}
    elseif(str_contains($action,'product'))$q=DB::table('products')->where('seller_id',$u->id);
    elseif(str_contains($action,'rider'))$q=DB::table('users')->where('company_id',$u->id)->where('role','rider');
    elseif(str_contains($action,'decline')){$q=DB::table('assignments')->where('rider_id',$u->id)->where('status','offered');$label='kind';}
    elseif(str_contains($action,'remit')){$q=DB::table('remittances');$u->role==='rider'?$q->where('rider_id',$u->id):$q->where('company_id',$u->id);$label='reference';}
    elseif(str_contains($action,'return')||str_contains($action,'dispute')){$q=DB::table('return_requests');if($u->role==='buyer')$q->where('buyer_id',$u->id);elseif($u->role==='seller')$q->where('seller_id',$u->id);$label='status';}
    elseif(str_contains($action,'review')){$q=DB::table('item_reviews')->where('seller_id',$u->id);$label='comment';}
    elseif(str_contains($action,'payout')){$q=DB::table('payouts');if($u->role==='seller')$q->where('seller_id',$u->id);$label='account';}
  }
  if(!$q)return null;$out=[];foreach($q->get() as $r){$text=$r->$label??'Record';$text.=isset($r->order_id)?' · order #'.$r->order_id:'';$text.=isset($r->email)?' · '.$r->email:'';$out[$r->$key]=$text.' (#'.$r->$key.')';}return $out;
 }
}
