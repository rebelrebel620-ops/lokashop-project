<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  if(!Schema::hasColumn('delivery_areas','barangay'))Schema::table('delivery_areas',fn(Blueprint $t)=>$t->string('barangay',120)->nullable());
  Schema::table('assignments',fn(Blueprint $t)=>$t->unsignedBigInteger('rider_id')->nullable()->change());
  if(!Schema::hasColumn('orders','shipping_commission')){
   Schema::table('orders',fn(Blueprint $t)=>$t->decimal('shipping_commission',12,2)->default(0));
   DB::table('orders')->update(['shipping_commission'=>DB::raw('ROUND(shipping_fee * 0.20, 2)')]);
  }
  DB::table('assignments')->where('kind','pickup')->whereIn('status',['requested','offered','accepted'])->update(['earnings'=>0]);
  foreach(DB::table('assignments as a')->join('parcels as p','p.id','=','a.parcel_id')->where('a.kind','delivery')->whereIn('a.status',['offered','accepted'])->select('a.id','p.order_id')->get() as $a){
   $o=DB::table('orders')->find($a->order_id);
   DB::table('assignments')->where('id',$a->id)->update(['earnings'=>round(max(0,$o->shipping_fee-$o->shipping_commission),2)]);
  }
  foreach(DB::table('parcels as p')->join('assignments as a','a.parcel_id','=','p.id')->where('p.status','PREPARING')->where('a.kind','pickup')->whereIn('a.status',['offered','accepted'])->whereNotNull('p.center_id')->select('p.id','p.order_id','p.center_id')->distinct()->get() as $p){
   $actor=DB::table('sorting_centers')->where('id',$p->center_id)->value('user_id');
   DB::table('parcels')->where('id',$p->id)->update(['status'=>'READY_FOR_PICKUP','updated_at'=>now()]);
   DB::table('orders')->where('id',$p->order_id)->update(['status'=>'READY_FOR_PICKUP','updated_at'=>now()]);
   DB::table('status_history')->insert(['parcel_id'=>$p->id,'from_status'=>'PREPARING','to_status'=>'READY_FOR_PICKUP','actor_id'=>$actor,'reason'=>'Repair verified pickup readiness','created_at'=>now(),'updated_at'=>now()]);
  }
 }
 public function down():void {
  // Preserve financial snapshots and unassigned pickup requests on rollback.
 }
};
