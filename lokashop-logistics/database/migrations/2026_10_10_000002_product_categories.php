<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  foreach(config('registration.categories',[]) as $name){
   DB::table('categories')->insertOrIgnore(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
  }
 }
 public function down(): void {
  // Retain categories because existing products may reference them.
 }
};
