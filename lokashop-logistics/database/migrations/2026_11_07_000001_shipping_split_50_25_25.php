<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasColumn('orders','shipping_split_version'))Schema::table('orders',fn(Blueprint $t)=>$t->unsignedTinyInteger('shipping_split_version')->default(1));
  if(!Schema::hasColumn('wallet_courier_payments','funding_source'))Schema::table('wallet_courier_payments',fn(Blueprint $t)=>$t->string('funding_source')->default('center'));
  if(!Schema::hasTable('shipping_distributions'))Schema::create('shipping_distributions',function(Blueprint $t){$t->unsignedBigInteger('order_id')->primary();$t->unsignedBigInteger('pool_cents');$t->unsignedBigInteger('center_cents');$t->unsignedBigInteger('driver_cents');$t->unsignedBigInteger('rider_cents');$t->unsignedBigInteger('paid_cents')->default(0);$t->unsignedBigInteger('unallocated_cents')->default(0);$t->timestamp('finalized_at')->nullable();$t->timestamps();});
  // Only switch open shipments whose shipping funds have never been credited.
  DB::table('orders')->whereNotIn('status',['DELIVERED','COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED','RETURNED','RETURN_TO_SENDER','LOST','DAMAGED'])->whereNotIn('id',DB::table('wallet_entries')->whereIn('kind',['shipping_income','logistics_commission'])->whereNotNull('order_id')->select('order_id'))->whereNotIn('id',DB::table('parcels as p')->join('assignments as a','a.parcel_id','=','p.id')->join('wallet_courier_payments as w','w.assignment_id','=','a.id')->select('p.order_id'))->update(['shipping_split_version'=>2,'shipping_commission'=>0]);
  DB::table('orders')->where('shipping_split_version',2)->whereNotIn('status',['DELIVERED','COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED','RETURNED','RETURN_TO_SENDER','LOST','DAMAGED'])->orderBy('id')->chunkById(100,function($orders){foreach($orders as $o){$parts=\App\Services\ShippingDistribution::portions($o->shipping_fee);foreach(DB::table('assignments')->whereIn('parcel_id',DB::table('parcels')->where('order_id',$o->id)->select('id'))->get() as $a)DB::table('assignments')->where('id',$a->id)->update(['earnings'=>$a->kind==='delivery'?$parts['rider']/100:0]);}});
 }
 public function down(): void {Schema::dropIfExists('shipping_distributions');if(Schema::hasColumn('wallet_courier_payments','funding_source'))Schema::table('wallet_courier_payments',fn(Blueprint $t)=>$t->dropColumn('funding_source'));if(Schema::hasColumn('orders','shipping_split_version'))Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn('shipping_split_version'));}
};
