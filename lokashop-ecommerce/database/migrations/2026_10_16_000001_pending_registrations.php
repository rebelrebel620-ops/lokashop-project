<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pending_registrations')) {
            Schema::create('pending_registrations', function (Blueprint $t) {
                $t->id();
                $t->string('email')->index();
                $t->longText('payload');
                $t->string('code_hash')->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->dateTime('sent_at')->nullable();
                $t->dateTime('code_expires_at')->nullable();
                $t->dateTime('expires_at')->index();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};