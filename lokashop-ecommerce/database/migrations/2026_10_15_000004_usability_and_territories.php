<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->string('account_status')->default('active')->index();$t->text('account_status_reason')->nullable();$t->string('courier_type')->default('rider');});
  Schema::table('sorting_centers',function(Blueprint $t){$t->string('municipality_key',64)->nullable()->unique();$t->string('city',120)->nullable();$t->string('province',120)->nullable();});
  Schema::table('parcels',function(Blueprint $t){$t->unsignedBigInteger('destination_center_id')->nullable()->index();});
  // Preserve legacy duplicates for administrator reconciliation; reserve each municipality once.
  foreach(DB::table('sorting_centers')->orderBy('id')->get() as $c){$a=DB::table('addresses')->where('user_id',$c->user_id)->orderByDesc('is_default')->first();if(!$a)continue;$key=\App\Services\Municipalities::key($a->province,$a->city);$data=['city'=>$a->city,'province'=>$a->province];if(!DB::table('sorting_centers')->where('municipality_key',$key)->exists())$data['municipality_key']=$key;DB::table('sorting_centers')->where('id',$c->id)->update($data);}
 }
 public function down():void { /* Deliberately retain records and territory claims. */ }
};
