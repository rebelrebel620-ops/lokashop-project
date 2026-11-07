<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Schema};
class LiveUpdates {
 public static function snapshot(): array {
  $user=auth()->user();$id=$user?(int)$user->id:0;$role=$user->role??'guest';$ctx=hash_init('sha256');hash_update($ctx,$id.':'.$role);
  $watch=function(string $table,$query=null,array $only=[])use($ctx){if(!Schema::hasTable($table))return;$cols=Schema::getColumnListing($table);$blocked=['password','remember_token','api_token','api_token_expires_at','activation_hash','otp_hash','delivery_otp_hash'];$selected=array_values(array_diff($only?array_intersect($cols,$only):$cols,$blocked));if(!$selected)return;$q=$query??DB::table($table);$q->select($selected);$key=in_array('id',$cols,true)?'id':(in_array('user_id',$cols,true)?'user_id':$cols[0]);$q->orderBy($key);hash_update($ctx,$table.':');foreach($q->cursor() as $row)hash_update($ctx,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));};
  if(in_array($role,['guest','buyer','seller','admin'],true)){
   foreach(['products','product_variations','categories','seller_businesses','promotions','bundle_deals','vouchers','item_reviews','announcements'] as $table)$watch($table);
   $watch('users',DB::table('users')->where('role','seller'),['id','name','active','account_status','approval_status','profile_photo']);
  }
  foreach(['sorting_centers','shipping_rates'] as $table)$watch($table,null,['id','user_id','name','active','city','province','barangay','municipality_key','base_fee','extra_kg_fee','origin_province','updated_at']);
  if(!$id){$path=(string)request()->query('page',request()->path());$parts=parse_url($path);$track=null;if(preg_match('~^/track/([^/]+)$~',$parts['path']??'', $m))$track=rawurldecode($m[1]);if(($parts['path']??'')==='/track'){parse_str($parts['query']??'',$params);$track=$params['code']??null;}if(is_string($track)&&strlen($track)<=100)$watch('parcels',DB::table('parcels')->where('tracking_code',$track),['id','tracking_code','status','updated_at']);return ['revision'=>hash_final($ctx),'authenticated'=>false];}
  $watch('users',DB::table('users')->where('id',$id),['id','name','email','phone','profile_photo','online','active','account_status','approval_status','company_id','courier_type','updated_at']);
  if(in_array($role,['admin','logistics_admin'],true))$watch('users',null,['id','name','email','phone','profile_photo','online','active','account_status','approval_status','company_id','courier_type','updated_at']);
  elseif($role==='sorting_center')$watch('users',DB::table('users')->where('company_id',$id),['id','name','online','active','account_status','approval_status','company_id','courier_type','updated_at']);
  $centers=DB::table('sorting_centers')->where('user_id',$id)->select('id');
  $orders=DB::table('orders');
  if($role==='buyer')$orders->where('buyer_id',$id);
  elseif($role==='seller')$orders->where('seller_id',$id);
  elseif($role==='rider')$orders->whereIn('id',DB::table('parcels')->whereIn('id',DB::table('assignments')->where('rider_id',$id)->select('parcel_id'))->select('order_id'));
  elseif($role==='sorting_center')$orders->whereIn('id',DB::table('parcels')->where(function($q)use($centers,$id){$q->whereIn('center_id',clone $centers);if(Schema::hasColumn('parcels','destination_center_id'))$q->orWhereIn('destination_center_id',clone $centers);$q->orWhereIn('id',DB::table('assignments as a')->join('users as r','r.id','=','a.rider_id')->where('r.company_id',$id)->select('a.parcel_id'));})->select('order_id'));
  elseif(!in_array($role,['admin','logistics_admin'],true))$orders->whereRaw('1=0');
  $orderIds=(clone $orders)->select('id');$parcelIds=DB::table('parcels')->whereIn('order_id',clone $orderIds)->select('id');
  $watch('orders',clone $orders);
  foreach(['order_items','payments','refunds','return_requests','order_vouchers','shipping_distributions'] as $table)$watch($table,DB::table($table)->whereIn('order_id',clone $orderIds));
  $watch('parcels',DB::table('parcels')->whereIn('id',clone $parcelIds));$watch('status_history',DB::table('status_history')->whereIn('parcel_id',clone $parcelIds));
  $a=DB::table('assignments')->whereIn('parcel_id',clone $parcelIds);if($role==='rider')$a->where('rider_id',$id);$watch('assignments',$a);
  foreach(['wallet_accounts','wallet_entries','notifications'] as $table)$watch($table,DB::table($table)->where('user_id',$id));
  $w=DB::table('wallet_courier_payments');if($role==='sorting_center')$w->where('center_user_id',$id);elseif($role==='rider')$w->where('courier_user_id',$id);elseif(!in_array($role,['admin','logistics_admin'],true))$w->whereRaw('1=0');$watch('wallet_courier_payments',$w);
  if($role==='admin'){$watch('payouts');$watch('wallet_entries',DB::table('wallet_entries')->whereIn('kind',['sale','earnings_import','commission']));}elseif($role==='seller')$watch('payouts',DB::table('payouts')->where('seller_id',$id));
  $watch('addresses',DB::table('addresses')->where(fn($q)=>$q->where('user_id',$id)->orWhereIn('id',DB::table('orders')->whereIn('id',clone $orderIds)->select('address_id'))));
  $watch('messages',DB::table('messages')->where(fn($q)=>$q->where('sender_id',$id)->orWhere('recipient_id',$id)));
  foreach(['cart_items','wishlists','store_follows','voucher_claims','voucher_redemptions'] as $table)$watch($table,DB::table($table)->where('buyer_id',$id));
  foreach(['cod_collections','remittances'] as $table){$q=DB::table($table);if($role==='rider')$q->where('rider_id',$id);elseif($role==='sorting_center')$q->where('company_id',$id);elseif(!in_array($role,['admin','logistics_admin'],true))$q->whereRaw('1=0');$watch($table,$q);}
  $trips=DB::table('driver_trips');if($role==='rider')$trips->where('driver_id',$id);elseif($role==='sorting_center')$trips->where(fn($q)=>$q->whereIn('origin_center_id',clone $centers)->orWhereIn('id',DB::table('driver_trip_stops')->whereIn('center_id',clone $centers)->select('trip_id')));elseif(!in_array($role,['admin','logistics_admin'],true))$trips->whereRaw('1=0');
  $tripIds=(clone $trips)->select('id');$watch('driver_trips',$trips);foreach(['driver_trip_stops','driver_trip_items'] as $table)$watch($table,DB::table($table)->whereIn('trip_id',clone $tripIds));
  $areas=DB::table('delivery_areas');if($role==='sorting_center')$areas->whereIn('center_id',clone $centers);elseif($role==='rider')$areas->where('rider_id',$id);elseif(!in_array($role,['admin','logistics_admin'],true))$areas->whereRaw('1=0');$watch('delivery_areas',$areas);
  foreach(['complaints','appeals','courier_appeals','registration_documents'] as $table){if(!Schema::hasTable($table))continue;$cols=Schema::getColumnListing($table);$q=DB::table($table);if(!in_array($role,['admin','logistics_admin'],true)){$owner=in_array('user_id',$cols,true)?'user_id':(in_array('buyer_id',$cols,true)?'buyer_id':null);if($owner)$q->where($owner,$id);else $q->whereRaw('1=0');}$watch($table,$q);}
  return ['revision'=>hash_final($ctx),'authenticated'=>true];
 }
}
