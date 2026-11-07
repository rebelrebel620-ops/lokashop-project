<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {if(!Schema::hasTable('wallet_courier_payments'))Schema::create('wallet_courier_payments',function(Blueprint $t){$t->unsignedBigInteger('assignment_id')->primary();$t->unsignedBigInteger('center_user_id')->index();$t->unsignedBigInteger('courier_user_id')->index();$t->unsignedBigInteger('amount_cents');$t->string('status')->default('pending');$t->timestamp('paid_at')->nullable();$t->timestamps();});}
 public function down(): void {Schema::dropIfExists('wallet_courier_payments');}
};
