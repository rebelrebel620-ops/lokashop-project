<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasColumn('orders','cod_rounding_adjustment'))Schema::table('orders',fn(Blueprint $t)=>$t->decimal('cod_rounding_adjustment',12,2)->default(0));
  DB::table('orders')->whereNotIn('status',['DELIVERED','COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED','RETURNED','RETURN_TO_SENDER','LOST','DAMAGED'])->whereExists(fn($q)=>$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','orders.id')->where('payments.method','cod')->where('payments.status','due_on_delivery'))->orderBy('id')->chunkById(100,function($orders){foreach($orders as $row)DB::transaction(function()use($row){$o=DB::table('orders')->where('id',$row->id)->lockForUpdate()->first();$p=DB::table('payments')->where('order_id',$o->id)->orderByDesc('id')->lockForUpdate()->first();if(!$p||$p->method!=='cod'||$p->status!=='due_on_delivery')return;$raw=\App\Services\Support::money($o->total-$o->cod_rounding_adjustment);$total=\App\Services\CodAmount::round($raw);DB::table('orders')->where('id',$o->id)->update(['total'=>$total,'cod_rounding_adjustment'=>\App\Services\Support::money($total-$raw)]);DB::table('payments')->where('id',$p->id)->update(['amount'=>$total]);});});
 }
 public function down(): void {if(Schema::hasColumn('orders','cod_rounding_adjustment'))Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn('cod_rounding_adjustment'));}
};
