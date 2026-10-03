<?php

\Illuminate\Support\Facades\Artisan::command('lokashop:create-logistics-admin {email}', function () {
    $email = strtolower(trim($this->argument('email')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('Enter a valid email address.'); return 1; }
    if (\Illuminate\Support\Facades\DB::table('users')->where('email', $email)->exists()) {
        $this->error('This email already has an account. Use a new email for the logistics admin.'); return 1;
    }
    $name = trim((string) $this->ask('Admin name'));
    $phone = trim((string) $this->ask('Phone'));
    $password = (string) $this->secret('Password (at least 8 characters)');
    $confirmation = (string) $this->secret('Confirm password');
    if ($name === '' || mb_strlen($name) > 150 || mb_strlen($phone) > 40 || strlen($email) > 190 || strlen($password) < 8 || $password !== $confirmation) {
        $this->error('Check your name, phone and email lengths, and matching passwords of at least 8 characters.'); return 1;
    }
    \Illuminate\Support\Facades\DB::table('users')->insert([
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'password' => \Illuminate\Support\Facades\Hash::make($password),
        'role' => 'logistics_admin', 'approval_status' => 'approved', 'active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->info('Logistics admin created. Log in on the logistics website using this email and password.');
    return 0;
})->purpose('Create a separate logistics administrator');
