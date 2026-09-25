<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void { foreach (array_filter(array_map('trim',explode(';',file_get_contents(database_path('schema/lokashop_schema.sql'))))) as $sql) DB::statement($sql); }
 public function down(): void { foreach (['policies','announcements','complaints','notifications','messages','ratings','status_history','assignments','parcels','payments','order_items','orders','vouchers','cart_items','product_variations','products','categories','addresses','delivery_areas','sorting_centers','seller_businesses','registration_documents','users'] as $table) DB::statement('DROP TABLE IF EXISTS `'.$table.'`'); }
};
