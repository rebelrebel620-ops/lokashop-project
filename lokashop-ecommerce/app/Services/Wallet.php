<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

/** Demo pesos only. Balances and ledger movements use integer centavos. */
class Wallet {
 public static function cents($amount): int {
  $s=(string)$amount;
  if(!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',$s))Support::fail('amount','Enter a valid amount with at most two decimal places.');
  [$whole,$fraction]=array_pad(explode('.',$s),2,'');
  return (int)$whole*100+(int)str_pad($fraction,2,'0');
 }
 public static function balance(int $user): int {return (int)(DB::table('wallet_accounts')->where('user_id',$user)->value('balance_cents')??0);}
 private static function lock(array $users): void {
  sort($users,SORT_NUMERIC);
  foreach(array_unique($users) as $id){DB::table('wallet_accounts')->insertOrIgnore(['user_id'=>$id,'balance_cents'=>0,'created_at'=>now(),'updated_at'=>now()]);DB::table('wallet_accounts')->where('user_id',$id)->lockForUpdate()->first();}
 }
 private static function entry(int $user,int $delta,string $reference,string $kind,string $description,?int $order=null,?int $other=null): void {
  if(DB::table('wallet_entries')->where('reference',$reference)->exists())return;
  $after=self::balance($user)+$delta;
  if($after<0)Support::fail('payment_method','Insufficient wallet balance. Use available earned funds or choose COD.');
  if($after>99999999900)Support::fail('amount','Wallet balance limit reached.');
  DB::table('wallet_accounts')->where('user_id',$user)->update(['balance_cents'=>$after,'updated_at'=>now()]);
  DB::table('wallet_entries')->insert(['user_id'=>$user,'reference'=>$reference,'kind'=>$kind,'amount_cents'=>$delta,'balance_after_cents'=>$after,'order_id'=>$order,'counterparty_id'=>$other,'description'=>$description,'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function transfer(int $from,int $to,int $amount,string $key): void {
  if($from===$to)Support::fail('recipient','Choose another user.');
  if($amount<100||$amount>10000000)Support::fail('amount','Transfer between PHP 1 and PHP 100,000.');
  DB::transaction(function()use($from,$to,$amount,$key){
   $recipient=DB::table('users')->where('id',$to)->whereIn('role',['buyer','seller','admin','sorting_center','rider','logistics_admin'])->where('active',1)->where('account_status','active')->where('approval_status','approved')->first();
   if(!$recipient)Support::fail('recipient','No active wallet user matches that email.');
   self::lock([$from,$to]);
   if(DB::table('wallet_entries')->where('reference','send:'.$from.':'.$key)->exists())return;
   self::entry($from,-$amount,'send:'.$from.':'.$key,'transfer_out','Sent to '.$recipient->name,null,$to);
   self::entry($to,$amount,'receive:'.$from.':'.$key,'transfer_in','Received from LokaShop user #'.$from,null,$from);
  });
 }
 public static function pay(int $buyer,int $order,$amount): void {
  DB::transaction(function()use($buyer,$order,$amount){self::lock([$buyer]);self::entry($buyer,-self::cents($amount),'order-pay:'.$order,'order_payment','Paid order #'.$order,$order);});
 }
 public static function refund($order): void {
  DB::transaction(function()use($order){
   $o=DB::table('orders')->where('id',$order->id)->lockForUpdate()->first();
   $p=DB::table('payments')->where('order_id',$o->id)->where('method','wallet')->orderByDesc('id')->lockForUpdate()->first();
   if(!$p||$p->status!=='paid')return;
   if(DB::table('wallet_entries')->where('reference','order-release:'.$o->id)->exists())Support::fail('refund','Wallet funds already released to the seller.');
   self::lock([(int)$o->buyer_id]);
   self::entry((int)$o->buyer_id,self::cents($p->amount),'order-refund:'.$o->id,'refund','Refund for order #'.$o->id,$o->id);
   DB::table('payments')->where('id',$p->id)->update(['status'=>'refunded','updated_at'=>now()]);
   DB::table('refunds')->updateOrInsert(['order_id'=>$o->id],['amount'=>$p->amount,'method'=>'wallet','status'=>'paid','reference'=>'order-refund:'.$o->id,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('orders')->where('id',$o->id)->update(['refunded_at'=>now(),'updated_at'=>now()]);
  });
 }
 public static function release($order): void {
  DB::transaction(function()use($order){
   $o=DB::table('orders')->where('id',$order->id)->lockForUpdate()->first();
   $p=DB::table('payments')->where('order_id',$o->id)->orderByDesc('id')->first();
   if(!$p||$p->status!=='paid'||$o->refunded_at)return;
   if($o->status!=='COMPLETED')Support::fail('order','Order receipt must be confirmed by the buyer or the seven-day automatic completion before seller settlement.');
   $amount=max(0,self::cents($o->merchandise_total)-self::cents($o->store_discount)-self::cents($o->commission));
   $admin=DB::table('users')->where('role','admin')->where('active',1)->where('approval_status','approved')->where('account_status','active')->orderBy('id')->value('id');
   self::lock(array_filter([(int)$o->seller_id,$admin?(int)$admin:null]));self::entry((int)$o->seller_id,$amount,'order-release:'.$o->id,'sale','Order #'.$o->id.' earnings after store discount and commission',$o->id,(int)$o->buyer_id);
   if($admin)self::entry((int)$admin,self::cents($o->commission),'platform-commission:'.$o->id,'commission','Platform commission for order #'.$o->id,$o->id,(int)$o->seller_id);
  });
 }
 public static function creditEarnings(int $user,int $amount,string $reference,string $kind,string $description,?int $order=null): void {
  if($amount<0)Support::fail('amount','Invalid earnings amount.');
  DB::transaction(function()use($user,$amount,$reference,$kind,$description,$order){self::lock([$user]);self::entry($user,$amount,$reference,$kind,$description,$order);});
 }
 public static function payCourier(int $center,int $courier,int $amount,int $assignment): bool {
  return DB::transaction(function()use($center,$courier,$amount,$assignment){
   if($amount<0)Support::fail('amount','Invalid courier earnings.');
   self::lock([$center,$courier]);$ref='courier-pay:'.$assignment;
   // Completed work belongs to the courier even when center funding is outstanding.
   self::entry($courier,$amount,'courier-earn:'.$assignment,'courier_earnings','Earnings for completed job #'.$assignment,null,$center);
   if(DB::table('wallet_entries')->where('reference',$ref)->exists())return true;
   if(self::balance($center)<$amount)return false;
   self::entry($center,-$amount,$ref,'courier_payment','Settlement for courier job #'.$assignment,null,$courier);return true;
  });
 }
}
