<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Schema};
class WalletAutomation {
 public static function firstAdmin(string $role): ?int {return DB::table('users')->where('role',$role)->where('active',1)->where('account_status','active')->where('approval_status','approved')->orderBy('id')->value('id');}
 public static function shippingCommission(int $id): void {
  $admin=self::firstAdmin('logistics_admin');if(!$admin)return;
  DB::transaction(function()use($id,$admin){$o=DB::table('orders')->where('id',$id)->lockForUpdate()->first();if(!$o||ShippingDistribution::modern($o)||!in_array($o->status,['DELIVERED','COMPLETED'],true)||$o->refunded_at)return;
   if(!DB::table('payments')->where('order_id',$id)->where('status','paid')->exists())return;
   Wallet::creditEarnings($admin,Wallet::cents($o->shipping_commission),'shipping-commission:'.$id,'logistics_commission','Shipping commission for order #'.$id,$id);
  });
 }
 public static function syncUser(int $id): void {
  $u=DB::table('users')->find($id);if(!$u||!$u->active||$u->account_status!=='active'||$u->approval_status!=='approved')return;
  if($u->role==='seller')foreach(DB::table('orders')->where('seller_id',$id)->where('status','COMPLETED')->whereNull('refunded_at')->whereNotIn('id',DB::table('wallet_entries')->where('kind','sale')->whereNotNull('order_id')->select('order_id'))->whereExists(fn($q)=>$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','orders.id')->where('payments.method','wallet')->where('payments.status','paid'))->orderBy('id')->limit(100)->get() as $o)Wallet::release($o);
  if($u->role==='seller')DB::transaction(function()use($id){DB::table('users')->where('id',$id)->lockForUpdate()->first();if(Commerce::available($id)>0)WalletEarnings::importSeller($id,'automatic');});
  if($u->role==='admin'&&self::firstAdmin('admin')===$id)WalletEarnings::collectAdmin($id);
  if($u->role==='logistics_admin'&&self::firstAdmin('logistics_admin')===$id){foreach(DB::table('orders')->where('shipping_split_version',1)->whereIn('status',['DELIVERED','COMPLETED'])->whereNull('refunded_at')->whereNotIn('id',DB::table('wallet_entries')->where('kind','logistics_commission')->whereNotNull('order_id')->select('order_id'))->whereExists(fn($q)=>$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','orders.id')->where('payments.status','paid'))->orderBy('id')->limit(100)->pluck('id') as $oid)self::shippingCommission($oid);}
  if($u->role==='rider')WalletEarnings::syncCourier($id);
  if($u->role==='sorting_center'){
   $center=DB::table('sorting_centers')->where('user_id',$id)->value('id');if(!$center)return;
   $jobs=DB::table('assignments as a')->join('users as r','r.id','=','a.rider_id')->where('a.status','completed')->whereIn('a.kind',['delivery','transfer'])->whereExists(fn($q)=>$q->selectRaw('1')->from('parcels as sp')->join('orders as so','so.id','=','sp.order_id')->whereColumn('sp.id','a.parcel_id')->where(fn($q)=>$q->where('so.shipping_split_version',1)->orWhereIn('so.status',['DELIVERED','COMPLETED'])));
   if(Schema::hasColumn('assignments','handling_center_id'))$jobs->where(fn($q)=>$q->where('a.handling_center_id',$center)->orWhere(fn($q)=>$q->whereNull('a.handling_center_id')->where('r.company_id',$id)));else $jobs->where('r.company_id',$id);
   $ref=DB::getDriverName()==='sqlite'?"'courier-earn:' || a.id":"CONCAT('courier-earn:', a.id)";
   foreach((clone $jobs)->whereNotExists(fn($q)=>$q->selectRaw('1')->from('wallet_entries')->whereRaw('wallet_entries.reference = '.$ref))->orderBy('a.id')->limit(100)->pluck('a.id') as $aid)WalletEarnings::queue($aid);
   $deliveries=(clone $jobs)->join('parcels as p','p.id','=','a.parcel_id')->join('orders as o','o.id','=','p.order_id')->where('a.kind','delivery')->where('o.shipping_split_version',1)->whereIn('o.status',['DELIVERED','COMPLETED'])->whereNull('o.refunded_at')->whereNotIn('o.id',DB::table('wallet_entries')->where('kind','shipping_income')->whereNotNull('order_id')->select('order_id'))->whereExists(fn($q)=>$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','o.id')->where('payments.status','paid'));
   foreach($deliveries->orderBy('a.id')->limit(100)->pluck('a.id') as $aid)WalletEarnings::delivery($aid);
   WalletEarnings::payPending($id);
  }
 }
 public static function run(): void {foreach(DB::table('orders')->where('shipping_split_version',2)->whereIn('status',['DELIVERED','COMPLETED'])->whereNotIn('id',DB::table('shipping_distributions')->whereNotNull('finalized_at')->select('order_id'))->whereExists(fn($q)=>$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','orders.id')->where('payments.status','paid'))->orderBy('id')->limit(100)->pluck('id') as $oid)ShippingDistribution::finalize($oid);DB::table('users')->whereIn('role',['seller','admin','logistics_admin','sorting_center','rider'])->where('active',1)->where('account_status','active')->where('approval_status','approved')->orderBy('id')->chunkById(100,function($users){foreach($users as $u)self::syncUser((int)$u->id);});}
}
