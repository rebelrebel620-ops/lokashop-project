<?php

\Illuminate\Support\Facades\Artisan::command('lokashop:approve-pending-buyers', function () {
    $count = \Illuminate\Support\Facades\DB::table('users')->where('role', 'buyer')
        ->where('approval_status', 'pending')->where('active', 1)
        ->update(['approval_status' => 'approved', 'updated_at' => now()]);
    $this->info("Approved {$count} existing pending buyer accounts. Rejected and disabled accounts were left unchanged.");
    return 0;
})->purpose('Apply automatic buyer approval to existing pending active buyers');

\Illuminate\Support\Facades\Artisan::command('lokashop:maintenance',function(){\App\Services\Maintenance::run();$this->info('Maintenance completed.');})->purpose('Expire stale offers/sessions, unpaid orders and complete eligible deliveries');
\Illuminate\Support\Facades\Schedule::command('lokashop:maintenance')->everyMinute()->withoutOverlapping();

\Illuminate\Support\Facades\Artisan::command('lokashop:sync-storage',function(){
 foreach(['local'=>'private','public'=>'public'] as $disk=>$dir){$target=config('filesystems.disks.'.$disk.'.root');\Illuminate\Support\Facades\File::ensureDirectoryExists($target);$source=storage_path('app/'.$dir);if(!is_dir($source))continue;foreach(\Illuminate\Support\Facades\File::allFiles($source) as $file){$relative=$file->getRelativePathname();$destination=$target.DIRECTORY_SEPARATOR.$relative;\Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($destination));if(!file_exists($destination))copy($file->getPathname(),$destination);elseif(hash_file('sha256',$destination)!==hash_file('sha256',$file->getPathname()))throw new RuntimeException('Storage conflict: '.$relative);}}
 $this->info('Existing uploads copied to shared storage; originals were retained.');
})->purpose('Copy old uploads into shared storage without overwriting conflicts');
\Illuminate\Support\Facades\Artisan::command('lokashop:create-admin {email}',function(){
 $email=mb_strtolower(trim($this->argument('email')));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email.');
 if(\Illuminate\Support\Facades\DB::table('users')->where('email',$email)->exists())throw new RuntimeException('This email already exists. No account was changed.');
 $password=$this->secret('New administrator password (8+ characters with upper/lowercase, number and symbol)');
 if(strlen($password)<8||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password)||!preg_match('/[^A-Za-z0-9]/',$password))throw new RuntimeException('Password does not meet the complexity rule.');
 $role=config('app.site_kind')==='logistics'?'logistics_admin':'admin';\Illuminate\Support\Facades\DB::table('users')->insert(['name'=>'Administrator','email'=>$email,'password'=>\Illuminate\Support\Facades\Hash::make($password),'role'=>$role,'approval_status'=>'approved','active'=>1,'email_verified_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);$this->info('Administrator created.');
})->purpose('Create an administrator without shipping a default password');

\Illuminate\Support\Facades\Schedule::call(fn()=>\App\Services\PendingRegistration::purge())->hourly();

\Illuminate\Support\Facades\Artisan::command('lokashop:delivery-check', function () {
 $this->info('Delivery integration configuration');
 $this->line('Database: '.config('database.connections.'.config('database.default').'.database'));
 $root=config('filesystems.disks.local.root');$this->line('Private storage: '.$root);
 $this->line('Both websites MUST print the same database and private-storage path.');
 try {
  $disk=\Illuminate\Support\Facades\Storage::disk('local');
  $probe='delivery-proofs/.connection-check-'.bin2hex(random_bytes(8));
  $disk->put($probe,'test');$disk->delete($probe);$this->info('Private storage is writable.');
  $missing=0;
  foreach(\Illuminate\Support\Facades\DB::table('parcels')->whereNotNull('proof_path')->cursor() as $parcel)if(!$disk->exists($parcel->proof_path))$missing++;
  $this->line('Delivery records with missing proof files: '.$missing);
  if($missing)$this->warn('Run lokashop:sync-storage in the original logistics folder, or restore the shared proof folder from backup.');
  return $missing ? 1 : 0;
 } catch(\Throwable $error) {
  $this->error('Database or shared-storage check failed: '.$error->getMessage());return 1;
 }
})->purpose('Check the database and shared storage used by courier proof photos');


\Illuminate\Support\Facades\Artisan::command('lokashop:check-config {--to= : Send a test message to your own email}', function () {
    $failed = false;
    $mailer = config('mail.default');
    $this->line('Mailer: '.$mailer);
    if ($mailer !== 'smtp') { $this->warn('Email is not using SMTP. Check MAIL_MAILER.'); $failed = true; }
    foreach (['host','port','username','password'] as $key) {
        $present = (string) config('mail.mailers.smtp.'.$key) !== '';
        $this->line('SMTP '.$key.': '.($present ? 'configured' : 'MISSING'));
        if (!$present) $failed = true;
    }
    $this->line('Application URL: '.config('app.url'));
    try { \Illuminate\Support\Facades\DB::connection()->getPdo(); $this->info('Database connection OK.'); }
    catch (\Throwable $e) { $this->error('Database connection failed. Check DB settings and that MySQL is running.'); $failed = true; }
    foreach (['local','public'] as $disk) {
        $path = config('filesystems.disks.'.$disk.'.root');
        if (is_dir($path) && is_writable($path)) $this->info($disk.' storage is writable.');
        else { $this->error($disk.' storage is missing or not writable. Check shared storage paths.'); $failed = true; }
    }
    $to = trim((string) $this->option('to'));
    if ($to !== '') {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $this->error('Enter a valid test email.'); return 1; }
        if ($mailer !== 'smtp') { $this->error('Test not sent: configure SMTP first.'); return 1; }
        try {
            \Illuminate\Support\Facades\Mail::raw('LokaShop SMTP test. Your app can submit email to the configured SMTP server. Check your inbox and spam folder.', fn($m) => $m->to($to)->subject('LokaShop email connection test'));
            $this->info('SMTP server accepted the message. Check your inbox/spam to confirm receipt.');
        } catch (\Throwable $e) {
            $this->error('Email submission failed. Check the Gmail app password, TLS/port, and network access.');
            $this->line('Error type: '.get_class($e)); $failed = true;
        }
    } else $this->line('No test message sent. Add --to=YOUR_EMAIL to test delivery.');
    return $failed ? 1 : 0;
})->purpose('Check local database, storage and email configuration without displaying secrets');
