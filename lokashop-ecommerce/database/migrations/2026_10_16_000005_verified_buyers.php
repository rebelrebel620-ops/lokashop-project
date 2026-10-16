<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {DB::table('users')->where('role','buyer')->whereNotNull('email_verified_at')->whereIn('approval_status',['pending','unverified'])->update(['approval_status'=>'approved','updated_at'=>now()]);}
 public function down():void { /* Email verification remains valid. */ }
};
