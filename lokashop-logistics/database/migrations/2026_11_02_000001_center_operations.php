<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
 Schema::create('courier_appeals',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained('users');$t->text('message');$t->string('status',20)->default('pending');$t->unsignedBigInteger('reviewed_by')->nullable();$t->text('review_reason')->nullable();$t->timestamps();$t->index(['user_id','status']);});
 Schema::table('assignments',fn(Blueprint $t)=>$t->unsignedBigInteger('handling_center_id')->nullable()->index());
 DB::table('assignments')->orderBy('id')->chunkById(500,function($rows){foreach($rows as $a){$company=$a->rider_id?DB::table('users')->where('id',$a->rider_id)->value('company_id'):null;$center=$company?DB::table('sorting_centers')->where('user_id',$company)->value('id'):null;if(!$center&&$a->status!=='completed')$center=DB::table('parcels')->where('id',$a->parcel_id)->value('center_id');if($center)DB::table('assignments')->where('id',$a->id)->update(['handling_center_id'=>$center]);}});
 }
 public function down():void {Schema::dropIfExists('courier_appeals');Schema::table('assignments',fn(Blueprint $t)=>$t->dropColumn('handling_center_id'));}
};
