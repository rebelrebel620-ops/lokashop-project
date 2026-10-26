<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  foreach(DB::table('parcels')->where('status','PICKED_UP')->get() as $p){$a=DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->orderByDesc('id')->first();if($a&&$a->status==='completed')DB::table('assignments')->where('id',$a->id)->update(['status'=>'accepted','earnings'=>0,'expires_at'=>null]);}
  DB::table('assignments')->where('kind','pickup')->whereIn('status',['requested','accepted'])->update(['expires_at'=>null]);
  foreach(DB::table('assignments')->where('kind','pickup')->where('status','offered')->get() as $a)DB::table('assignments')->where('id',$a->id)->update(['expires_at'=>\Illuminate\Support\Carbon::parse($a->updated_at??$a->created_at)->addHours(3)]);
 }
 public function down():void {}
};
