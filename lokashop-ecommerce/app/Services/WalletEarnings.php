<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class WalletEarnings {
 // Call only after the assignment has been completed by the normal workflow.
 public static function delivery(int $assignment): void {
  DB::transaction(function()use($assignment){
   $a=DB::table('assignments')->where('id',$assignment)->lockForUpdate()->first();
   if(!$a||$a->status!=='completed'||$a->kind!=='delivery')return;
   $p=DB::table('parcels')->find($a->parcel_id);if(!empty($p->return_request_id))return;$o=DB::table('orders')->where('id',$p->order_id)->lockForUpdate()->first();
   if(ShippingDistribution::modern($o)){ShippingDistribution::finalize($o->id);return;}
   $payment=DB::table('payments')->where('order_id',$o->id)->orderByDesc('id')->first();if(!$payment||$payment->status!=='paid')return;
   $center=self::centerUser($a);if(!$center)return;
   $income=max(0,Wallet::cents($o->shipping_fee)-Wallet::cents($o->shipping_commission));
   Wallet::creditEarnings($center,$income,'shipping-income:'.$o->id,'shipping_income','Delivery income for order #'.$o->id.' after shipping commission',$o->id);
   self::queue($assignment);
  });
 }
 private static function centerUser($assignment): ?int {
  $id=($assignment->handling_center_id??null)?DB::table('sorting_centers')->where('id',$assignment->handling_center_id)->value('user_id'):null;
  if(!$id)$id=DB::table('users')->where('id',$assignment->rider_id)->value('company_id');
  return $id&&DB::table('users')->where('id',$id)->where('role','sorting_center')->exists()?(int)$id:null;
 }
 public static function queue(int $assignment): void {
  DB::transaction(function()use($assignment){
   $a=DB::table('assignments')->where('id',$assignment)->lockForUpdate()->first();
   if(!$a||$a->status!=='completed'||!in_array($a->kind,['delivery','transfer'],true))return;
   if(!empty(DB::table('parcels')->find($a->parcel_id)->return_request_id))return;
   $order=DB::table('orders')->where('id',DB::table('parcels')->where('id',$a->parcel_id)->value('order_id'))->first();
   if(ShippingDistribution::modern($order)){ShippingDistribution::finalize($order->id);return;}
   $center=self::centerUser($a);if(!$center)return;
   DB::table('wallet_courier_payments')->insertOrIgnore(['assignment_id'=>$a->id,'center_user_id'=>$center,'courier_user_id'=>$a->rider_id,'amount_cents'=>Wallet::cents($a->earnings),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
   self::pay($a->id,$center);
  });
 }
 public static function pay(int $assignment,int $center): bool {
  return DB::transaction(function()use($assignment,$center){
   $row=DB::table('wallet_courier_payments')->where('assignment_id',$assignment)->lockForUpdate()->first();abort_unless($row&&$row->center_user_id==$center,403);
   if(($row->funding_source??'center')==='shipping_pool')return true;
   Wallet::payCourier($center,(int)$row->courier_user_id,(int)$row->amount_cents,$assignment);
   DB::table('wallet_courier_payments')->where('assignment_id',$assignment)->update(['status'=>'paid','paid_at'=>$row->paid_at??now(),'updated_at'=>now()]);return true;
  });
 }
 public static function payPending(int $center): void {
  foreach(self::unsettled()->where('center_user_id',$center)->orderBy('created_at')->orderBy('assignment_id')->limit(100)->pluck('assignment_id') as $id)self::pay($id,$center);
 }
 public static function importSeller(int $seller,string $key): void {
  DB::transaction(function()use($seller,$key){
   DB::table('users')->where('id',$seller)->lockForUpdate()->first();
   $amount=Commerce::available($seller);if($amount<=0)Support::fail('earnings','No available legacy earnings to move.');
   $id=DB::table('payouts')->insertGetId(['seller_id'=>$seller,'amount'=>$amount,'account'=>'LokaShop demo wallet','status'=>'paid','created_at'=>now(),'updated_at'=>now()]);
   Wallet::creditEarnings($seller,Wallet::cents($amount),'seller-earnings-import:'.$id,'earnings_import','Previous available earnings moved to wallet');
  });
 }
 public static function availableAdmin(){
  return CodSettlement::eligible(DB::table('orders'))->where('status','COMPLETED')->whereNull('refunded_at')->whereExists(function($q){$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','orders.id')->where('payments.status','paid');})->whereNotIn('id',DB::table('wallet_entries')->where('kind','commission')->whereNotNull('order_id')->select('order_id'));
 }
 public static function collectAdmin(int $admin): int {
  abort_unless(DB::table('users')->where('id',$admin)->where('role','admin')->where('active',1)->where('account_status','active')->where('approval_status','approved')->exists(),403);
  $count=0;
  foreach(self::availableAdmin()->orderBy('id')->limit(100)->pluck('id') as $id){$credited=DB::transaction(function()use($id,$admin){
   $o=DB::table('orders')->where('id',$id)->lockForUpdate()->first();
   if(!self::availableAdmin()->where('id',$id)->exists())return false;
   Wallet::creditEarnings($admin,Wallet::cents($o->commission),'platform-commission:'.$id,'commission','Platform commission for order #'.$id,$id);return true;
  });if($credited)$count++;}return $count;
 }

 public static function unsettled(){
  $reference=DB::getDriverName()==='sqlite'?"'courier-pay:' || wallet_courier_payments.assignment_id":"CONCAT('courier-pay:', wallet_courier_payments.assignment_id)";
  $q=DB::table('wallet_courier_payments');if(\Illuminate\Support\Facades\Schema::hasColumn('wallet_courier_payments','funding_source'))$q->where('funding_source','center');
  return $q->whereNotExists(fn($q)=>$q->selectRaw('1')->from('wallet_entries')->whereRaw('wallet_entries.reference = '.$reference));
 }
 public static function syncCourier(int $courier): int {
  abort_unless(DB::table('users')->where('id',$courier)->where('role','rider')->exists(),403);
  $count=0;
  // Only uncredited completed jobs are processed. Old paid jobs remain excluded.
  $reference=DB::getDriverName()==='sqlite'?"'courier-earn:' || assignments.id":"CONCAT('courier-earn:', assignments.id)";
  $ids=DB::table('assignments')->where('rider_id',$courier)->where('status','completed')->whereIn('kind',['delivery','transfer'])->whereExists(fn($q)=>$q->selectRaw('1')->from('parcels as sp')->join('orders as so','so.id','=','sp.order_id')->whereColumn('sp.id','assignments.parcel_id')->where(fn($q)=>$q->where('so.shipping_split_version',1)->orWhereIn('so.status',['DELIVERED','COMPLETED'])))->whereNotExists(fn($q)=>$q->selectRaw('1')->from('wallet_entries')->whereRaw('wallet_entries.reference = '.$reference))->orderBy('id')->limit(100)->pluck('id');
  foreach($ids as $id){self::queue($id);if(DB::table('wallet_entries')->where('reference','courier-earn:'.$id)->exists())$count++;}return $count;
 }

}
