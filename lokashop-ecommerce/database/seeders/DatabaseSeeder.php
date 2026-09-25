<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder { public function run(): void { foreach (array_filter(array_map('trim',explode(';',file_get_contents(database_path('seed.sql'))))) as $sql) DB::statement($sql); } }
