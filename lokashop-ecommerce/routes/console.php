<?php

\Illuminate\Support\Facades\Artisan::command('lokashop:approve-pending-buyers', function () {
    $count = \Illuminate\Support\Facades\DB::table('users')->where('role', 'buyer')
        ->where('approval_status', 'pending')->where('active', 1)
        ->update(['approval_status' => 'approved', 'updated_at' => now()]);
    $this->info("Approved {$count} existing pending buyer accounts. Rejected and disabled accounts were left unchanged.");
    return 0;
})->purpose('Apply automatic buyer approval to existing pending active buyers');
