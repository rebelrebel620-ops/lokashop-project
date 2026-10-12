<?php
// Disposable SQLite checks; does not connect to the configured MySQL database.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Mail,Hash};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\Registration;
use App\Http\Controllers\VerificationController;
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
    'session.driver'=>'array','hashing.bcrypt.rounds'=>4]);
DB::purge();
Illuminate\Support\Carbon::setTestNow('2026-10-10 12:00:00');
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('phone')->nullable();$t->string('password');$t->string('role');$t->string('approval_status');$t->boolean('active');$t->string('remember_token')->nullable();$t->timestamps();});
Schema::create('seller_businesses',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->unique();$t->string('name');$t->string('category')->nullable();$t->timestamps();});
Schema::create('addresses',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');foreach(['recipient','phone','street','barangay','city','province','postal_code'] as $c)$t->string($c);$t->boolean('is_default');$t->timestamps();});
Schema::create('registration_documents',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('kind');$t->string('path');$t->timestamps();});
$migration=require __DIR__.'/../database/migrations/2026_10_10_000001_registration_validation_and_otp.php';$migration->up();
$mailer=new class {public array $codes=[]; public function raw($text,$callback){preg_match('/code is ([0-9]{6})/',$text,$m);$this->codes[]=$m[1];$callback(new class {public function to($e){return $this;}public function subject($s){return $this;}});}};
$realMailer=Mail::getFacadeRoot();
Mail::swap($mailer);
$count=0;
function check($condition,$label){global $count;if(!$condition)throw new RuntimeException('FAIL: '.$label);$count++;}
function req($data){global $app;$r=Request::create('/register','POST',$data);$r->setLaravelSession($app['session']->driver());$app->instance('request',$r);return $r;}
$valid=['role'=>'buyer','first_name'=>'Juan Miguel','last_name'=>'Dela Cruz','email'=>'buyer@example.com','phone'=>'09171234567','date_of_birth'=>'2008-10-10','password'=>'Mamimili@2026','password_confirmation'=>'Mamimili@2026','terms'=>'1','region'=>'NCR','province'=>'Metro Manila','city'=>'Quezon City','barangay'=>'Commonwealth','street'=>'12 Sample St','postal_code'=>'1121'];
function rejected($data,$field,$label){$before=DB::table('users')->count();try{Registration::create(req($data));throw new RuntimeException('Accepted invalid case: '.$label);}catch(ValidationException $e){check(isset($e->errors()[$field]),$label);check(DB::table('users')->count()===$before,$label.' creates no account');}}
foreach(['first_name'=>'J','last_name'=>'Dela99','phone'=>'0917ABC4567','date_of_birth'=>'2008-10-11','terms'=>'0','email'=>'bademail','postal_code'=>'12345'] as $field=>$value)rejected(array_replace($valid,[$field=>$value]),$field,'Invalid '.$field);
foreach(['first_name','last_name','phone','date_of_birth','password','email','street','city'] as $field)rejected(array_replace($valid,[$field=>'']),$field,'Required '.$field);
foreach(['Ride@26','rider@2026','RIDER@2026','Rider@abcd','Rider2026'] as $password)rejected(array_replace($valid,['password'=>$password,'password_confirmation'=>$password]),'password','Password complexity');
rejected(array_replace($valid,['first_name'=>str_repeat('A',51)]),'first_name','51-character name');
rejected(array_replace($valid,['first_name'=>'Juan😀']),'first_name','Emoji name');
rejected(array_replace($valid,['date_of_birth'=>'2030-01-01']),'date_of_birth','Future DOB');
rejected(array_replace($valid,['password_confirmation'=>'Other@2026']),'password_confirmation','Password mismatch');
rejected(array_replace($valid,['email'=>'buyer@example']),'email','Email domain missing dot');
$r=req(array_replace($valid,['first_name'=>"Peña O'Brien-Sr.",'last_name'=>str_repeat('A',50),'email'=>' BUYER@EXAMPLE.COM ','phone'=>'+639171234567']));
$id=Registration::create($r);$r->session()->put('registration_user_id',$id);
$u=DB::table('users')->find($id);check($u->phone==='09171234567','Phone normalization');check($u->email==='buyer@example.com','Email normalization');check($u->approval_status==='unverified','Unverified initial state');check(Hash::check($valid['password'],$u->password),'Password hashed');check(DB::table('addresses')->where('user_id',$id)->count()===1,'One buyer address');
$login=req(['email'=>' BUYER@EXAMPLE.COM ','password'=>$valid['password']]);$loginResponse=(new App\Http\Controllers\CommonController())->login($login);check(str_ends_with($loginResponse->getTargetUrl(),'/verify-email'),'Unverified login redirects to OTP');check(!Illuminate\Support\Facades\Auth::check(),'Unverified login grants no session');
rejected(array_replace($valid,['email'=>'BUYER@EXAMPLE.COM','phone'=>'09170000002']),'email','Duplicate email case insensitive');
rejected(array_replace($valid,['email'=>'next@example.com','phone'=>'+639171234567']),'phone','Duplicate +63 mobile');
$controller=new VerificationController();
$bad=req(['otp'=>'000000']);$bad->session()->put('registration_user_id',$id);$controller->verify($bad);check(DB::table('users')->find($id)->approval_status==='unverified','Incorrect OTP blocked');
DB::table('registration_otps')->where('user_id',$id)->update(['expires_at'=>now()->subSecond()]);
$expired=req(['otp'=>$mailer->codes[0]]);$expired->session()->put('registration_user_id',$id);$controller->verify($expired);check(DB::table('users')->find($id)->approval_status==='unverified','Expired OTP blocked');
Illuminate\Support\Carbon::setTestNow(now()->addMinutes(2));$resend=req([]);$resend->session()->put('registration_user_id',$id);$controller->resend($resend);
check(count($mailer->codes)===2,'OTP resent');check(!Hash::check($mailer->codes[0],DB::table('registration_otps')->where('user_id',$id)->first()->code_hash),'Old OTP invalidated');
$good=req(['otp'=>$mailer->codes[1]]);$good->session()->put('registration_user_id',$id);$controller->verify($good);check(DB::table('users')->find($id)->approval_status==='approved','Buyer activated');check(DB::table('registration_otps')->count()===0,'OTP consumed');
try{$controller->form($good);throw new RuntimeException('Used OTP session accepted');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Consumed verification session blocked');}
$seller=array_replace($valid,['role'=>'seller','email'=>'seller@example.com','phone'=>'09170000003','business_name'=>'My Shop','shop_category'=>'Home & Living','seller_type'=>'Corporation','tin'=>'123-456-789-000']);
foreach(['business_name','shop_category','seller_type','tin','id_document','sec_document','bir_document'] as $field)rejected(array_replace($seller,[$field=>'']),$field,'Required seller '.$field);
$uploadRoot=sys_get_temp_dir().'/lokashop-check-'.bin2hex(random_bytes(6));mkdir($uploadRoot);
config(['filesystems.disks.local.root'=>$uploadRoot]);Illuminate\Support\Facades\Storage::forgetDisk('local');
function uploadRequest($data,$fields,$size=100){global $uploadRoot;
 $r=req($data);foreach($fields as $field){$path=$uploadRoot.'/'.$field.'.pdf';file_put_contents($path,"%PDF-1.4\n".str_repeat(' ',$size));clearstatcache(true,$path);$r->files->set($field,new Illuminate\Http\UploadedFile($path,$field.'.pdf','application/pdf',null,true));}return $r;
}
foreach(['Individual','Sole Proprietorship','Corporation'] as $index=>$type){
 $data=array_replace($seller,['email'=>'seller'.$index.'@example.com','phone'=>'0917000001'.$index,'business_name'=>'Shop '.$index,'seller_type'=>$type,'tin'=>'123-456-78'.$index.'-000']);
 $files=match($type){'Individual'=>['id_document'],'Sole Proprietorship'=>['id_document','dti_document','bir_document'],default=>['id_document','sec_document','bir_document']};
 $sellerRequest=uploadRequest($data,$files);$sellerId=Registration::create($sellerRequest);
 check(DB::table('registration_documents')->where('user_id',$sellerId)->count()===count($files),'Conditional seller documents '.$type);
 $verify=req(['otp'=>end($mailer->codes)]);$verify->session()->put('registration_user_id',$sellerId);$controller->verify($verify);
 check(DB::table('users')->find($sellerId)->approval_status==='pending','Seller stays pending after OTP '.$type);
}
rejected(array_replace($seller,['email'=>'spare@example.com','phone'=>'09170000020','business_name'=>'SHOP 0','tin'=>'111-222-333-000']),'business_name','Duplicate shop name');
rejected(array_replace($seller,['email'=>'spare@example.com','phone'=>'09170000020','business_name'=>'Spare shop','tin'=>'123-456-780-000']),'tin','Duplicate TIN');
$sizeData=array_replace($seller,['seller_type'=>'Individual','email'=>'size@example.com','phone'=>'09170000021','business_name'=>'Size test','tin'=>'111-222-333-001']);
try{Registration::create(uploadRequest($sizeData,['id_document'],5242880));throw new RuntimeException('Oversize upload accepted');}catch(ValidationException $e){check(isset($e->errors()['id_document']),'Upload over 5 MB rejected');}
$exactId=Registration::create(uploadRequest(array_replace($sizeData,['first_name'=>'Po']),['id_document'],5242871));check(DB::table('users')->find($exactId)!==null,'Exactly 5 MB and two-character name accepted');
$role=new App\Http\Middleware\Role();Illuminate\Support\Facades\Auth::loginUsingId($id);
check($role->handle(req([]),fn()=>true,'buyer')===true,'Active buyer allowed buyer pages');
foreach(['seller','admin','rider','sorting_center'] as $other){try{$role->handle(req([]),fn()=>true,$other);throw new RuntimeException('Wrong role allowed');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Buyer denied '.$other.' pages');}}
Illuminate\Support\Facades\Auth::logout();
// Exercise the actual local log-mail transport without sending external mail.
Mail::swap($realMailer);config(['mail.default'=>'log']);Registration::issueOtp($id,'local-check@example.com');check(DB::table('registration_otps')->where('user_id',$id)->exists(),'Log-mail OTP transport');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry){$entry->isDir()?rmdir($entry->getPathname()):unlink($entry->getPathname());}rmdir($uploadRoot);
// View compilation and rendering catches Blade errors and missing form variables.
foreach(['auth','verify','terms'] as $view){$r=req([]);$app['view']->share('errors',new Illuminate\Support\ViewErrorBag());$html=view($view,['register'=>true,'roles'=>['buyer','seller'],'email'=>'buyer@example.com'])->render();check(strlen($html)>100,'Render '.$view);if($view==='auth')check(str_contains($html,'name="first_name"')&&str_contains($html,'data-password="password"'),'Updated registration fields render');}
echo "PASS: {$count} registration and OTP checks. SQLite only; rerun on MySQL and in a browser.\n";
