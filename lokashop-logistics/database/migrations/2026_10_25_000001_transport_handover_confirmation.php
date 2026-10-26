<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  foreach(['driver_trip_stops','assignments'] as $table){
   if(!Schema::hasColumn($table,'handed_over_at'))Schema::table($table,fn(Blueprint $t)=>$t->dateTime('handed_over_at')->nullable());
   if(!Schema::hasColumn($table,'handed_over_by'))Schema::table($table,fn(Blueprint $t)=>$t->foreignId('handed_over_by')->nullable()->constrained('users'));
  }
  foreach(['driver_trip_stops','driver_trip_items','assignments'] as $table)if(!Schema::hasColumn($table,'received_by'))Schema::table($table,fn(Blueprint $t)=>$t->foreignId('received_by')->nullable()->constrained('users'));
 }
 public function down():void { /* Preserve handover and receipt evidence. */ }
};
