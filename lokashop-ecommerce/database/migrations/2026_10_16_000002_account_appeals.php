<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::create('account_appeals',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->index();$t->text('message');$t->string('status')->default('pending');$t->text('resolution')->nullable();$t->timestamps();});}
 public function down(): void {Schema::dropIfExists('account_appeals');}
};
