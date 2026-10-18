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
