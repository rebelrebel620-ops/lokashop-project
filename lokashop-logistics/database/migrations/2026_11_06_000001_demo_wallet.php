<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasTable('wallet_accounts'))Schema::create('wallet_accounts',function(Blueprint $t){$t->unsignedBigInteger('user_id')->primary();$t->unsignedBigInteger('balance_cents')->default(0);$t->timestamps();});
  if(!Schema::hasTable('wallet_entries'))Schema::create('wallet_entries',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->index();$t->string('reference',100)->unique();$t->string('kind',30);$t->bigInteger('amount_cents');$t->unsignedBigInteger('balance_after_cents');$t->unsignedBigInteger('order_id')->nullable()->index();$t->unsignedBigInteger('counterparty_id')->nullable();$t->string('description');$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('wallet_entries');Schema::dropIfExists('wallet_accounts');}
};
