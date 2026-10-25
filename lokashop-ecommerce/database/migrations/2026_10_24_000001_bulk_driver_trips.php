<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  if(!Schema::hasTable('driver_trips'))Schema::create('driver_trips',function(Blueprint $t){$t->id();$t->foreignId('origin_center_id')->constrained('sorting_centers');$t->foreignId('driver_id')->constrained('users');$t->string('status',30)->default('offered');$t->dateTime('expires_at')->nullable();$t->dateTime('accepted_at')->nullable();$t->dateTime('departed_at')->nullable();$t->dateTime('completed_at')->nullable();$t->timestamps();$t->index(['driver_id','status']);});
  if(!Schema::hasTable('driver_trip_stops'))Schema::create('driver_trip_stops',function(Blueprint $t){$t->id();$t->foreignId('trip_id')->constrained('driver_trips');$t->foreignId('center_id')->constrained('sorting_centers');$t->unsignedInteger('position');$t->dateTime('received_at')->nullable();$t->timestamps();$t->unique(['trip_id','center_id']);});
  if(!Schema::hasColumn('assignments','trip_id'))Schema::table('assignments',fn(Blueprint $t)=>$t->foreignId('trip_id')->nullable()->constrained('driver_trips'));
  if(!Schema::hasTable('driver_trip_items'))Schema::create('driver_trip_items',function(Blueprint $t){$t->id();$t->foreignId('trip_id')->constrained('driver_trips');$t->foreignId('stop_id')->constrained('driver_trip_stops');$t->foreignId('parcel_id')->constrained('parcels');$t->foreignId('assignment_id')->constrained('assignments');$t->dateTime('received_at')->nullable();$t->timestamps();$t->unique(['trip_id','parcel_id']);});
 }
 public function down():void { /* Retain transport and receipt history. */ }
};
