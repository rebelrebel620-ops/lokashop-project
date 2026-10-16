<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {if(!Schema::hasColumn('users','profile_photo'))Schema::table('users',fn(Blueprint $t)=>$t->string('profile_photo')->nullable());}
 public function down(): void { /* Retain user photos when rolling back application code. */ }
};
