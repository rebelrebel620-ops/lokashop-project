<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  foreach(['products'=>['video_path'],'return_requests'=>['video_path','pickup_snapshot','destination_snapshot'],'parcels'=>['return_request_id','return_proof_path'],'assignments'=>['handling_center_id']] as $table=>$columns) foreach($columns as $column) if(!Schema::hasColumn($table,$column)) Schema::table($table,function(Blueprint $t)use($column){if(in_array($column,['return_request_id','handling_center_id']))$t->unsignedBigInteger($column)->nullable()->index();else $t->text($column)->nullable();});
 }
 public function down(): void { /* Preserve uploaded media and return history on rollback. */ }
};
