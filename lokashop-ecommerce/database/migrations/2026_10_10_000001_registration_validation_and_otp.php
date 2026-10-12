<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // MySQL DDL may commit earlier changes even when a later statement fails.
        // Check each column so an interrupted installation can be resumed.
        $columns = [
            'first_name'=>fn(Blueprint $t)=>$t->string('first_name',50)->nullable(),
            'last_name'=>fn(Blueprint $t)=>$t->string('last_name',50)->nullable(),
            'date_of_birth'=>fn(Blueprint $t)=>$t->date('date_of_birth')->nullable(),
            'phone_normalized'=>fn(Blueprint $t)=>$t->string('phone_normalized',11)->nullable()->unique(),
            'tin'=>fn(Blueprint $t)=>$t->string('tin',15)->nullable()->unique(),
            'seller_type'=>fn(Blueprint $t)=>$t->string('seller_type',30)->nullable(),
            'region'=>fn(Blueprint $t)=>$t->string('region',120)->nullable(),
            'terms_accepted_at'=>fn(Blueprint $t)=>$t->dateTime('terms_accepted_at')->nullable(),
            'verification_required'=>fn(Blueprint $t)=>$t->boolean('verification_required')->default(false),
            'email_verified_at'=>fn(Blueprint $t)=>$t->dateTime('email_verified_at')->nullable(),
        ];
        foreach($columns as $column=>$add) {
            if(!Schema::hasColumn('users',$column)) Schema::table('users',$add);
        }
        if(!Schema::hasColumn('seller_businesses','name_normalized'))
            Schema::table('seller_businesses',fn(Blueprint $t)=>$t->string('name_normalized',180)->nullable()->unique());
        if(!Schema::hasTable('registration_otps')) Schema::create('registration_otps',function(Blueprint $t){
            $t->unsignedBigInteger('user_id')->primary(); $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->string('code_hash'); $t->dateTime('expires_at'); $t->dateTime('sent_at'); $t->unsignedInteger('attempts')->default(0);
        });
    }
    public function down(): void {
        Schema::dropIfExists('registration_otps');
        Schema::table('seller_businesses',function(Blueprint $t){$t->dropUnique(['name_normalized']);$t->dropColumn('name_normalized');});
        Schema::table('users',function(Blueprint $t){
            $t->dropUnique(['phone_normalized']);$t->dropUnique(['tin']);
            $t->dropColumn(['first_name','last_name','date_of_birth','phone_normalized','tin','seller_type','region','terms_accepted_at','verification_required','email_verified_at']);
        });
    }
};
