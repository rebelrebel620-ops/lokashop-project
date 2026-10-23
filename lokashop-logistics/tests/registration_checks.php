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
config(['app.key'=>'base64:'.base64_encode(str_repeat('T',32)),'database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
    'session.driver'=>'array','hashing.bcrypt.rounds'=>4]);
DB::purge();
Illuminate\Support\Carbon::setTestNow('2026-10-10 12:00:00');
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('phone')->nullable();$t->string('password');$t->string('role');$t->string('approval_status');$t->boolean('active');$t->string('remember_token')->nullable();$t->timestamps();});
Schema::create('seller_businesses',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->unique();$t->string('name');$t->string('category')->nullable();$t->timestamps();});
Schema::create('addresses',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');foreach(['recipient','phone','street','barangay','city','province','postal_code'] as $c)$t->string($c);$t->boolean('is_default');$t->timestamps();});
Schema::create('registration_documents',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('kind');$t->string('path');$t->timestamps();});
$migration=require __DIR__.'/../database/migrations/2026_10_10_000001_registration_validation_and_otp.php';$migration->up();
Schema::table('users',function(Blueprint $t){$t->dateTime('last_seen_at')->nullable();$t->boolean('online')->default(false);$t->string('account_status')->default('active');});
$mailer=new class {public array $codes=[]; public array $messages=[]; public function raw($text,$callback){$this->messages[]=$text;preg_match('/code is ([0-9]{6})/',$text,$m);if(isset($m[1]))$this->codes[]=$m[1];$callback(new class {public function to($e){return $this;}public function subject($s){return $this;}});}};
$realMailer=Mail::getFacadeRoot();
Mail::swap($mailer);
$mPending=require __DIR__.'/../database/migrations/2026_10_16_000001_pending_registrations.php';$mPending->up();
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
$id=Registration::create(req($valid));
check(DB::table('users')->count()===0,'No user exists before verification');
check(count(array_filter($mailer->messages,fn($text)=>str_contains($text,'your account has been created')))===0,'No account-created email before OTP verification');
check(DB::table('addresses')->count()===0,'No user address exists before verification');
check(DB::table('pending_registrations')->count()===1,'Only a temporary application exists');
$pending=App\Services\PendingRegistration::applicant($id);
check(Hash::check($valid['password'],$pending->password),'Pending password is hashed');
check(App\Services\PendingRegistration::verify($id,'000000')!==null,'Wrong code rejected');
check(DB::table('users')->count()===0,'Wrong code creates no account');
DB::table('pending_registrations')->where('id',$id)->update(['code_expires_at'=>now()->subSecond()]);
check(App\Services\PendingRegistration::verify($id,$mailer->codes[0])!==null,'Expired code rejected');
Illuminate\Support\Carbon::setTestNow(now()->addMinutes(2));Registration::issueOtp($id,$valid['email']);
check(!Hash::check($mailer->codes[0],DB::table('pending_registrations')->find($id)->code_hash),'Resend invalidates old code');
check(App\Services\PendingRegistration::verify($id,end($mailer->codes))===null,'Correct code completes registration');
$u=DB::table('users')->where('email',$valid['email'])->first();
check($u&&$u->approval_status==='approved'&&$u->email_verified_at,'Buyer created and verified atomically');
check(count(array_filter($mailer->messages,fn($text)=>str_contains($text,'your account has been created')))===1,'Exactly one account-created email after OTP verification');
check(DB::table('addresses')->where('user_id',$u->id)->count()===1,'Address created with account');
check(!DB::table('pending_registrations')->where('id',$id)->exists(),'Temporary application consumed');
check(App\Services\PendingRegistration::verify($id,end($mailer->codes))!==null,'Code cannot be reused');
rejected(array_replace($valid,['email'=>'BUYER@EXAMPLE.COM','phone'=>'09170000002']),'email','Verified duplicate email rejected');
rejected(array_replace($valid,['email'=>'next@example.com','phone'=>'+639171234567']),'phone','Verified duplicate phone rejected');
$abandoned=Registration::create(req(array_replace($valid,['email'=>'abandoned@example.com','phone'=>'09170000003'])));
check(!DB::table('users')->where('email','abandoned@example.com')->exists(),'Abandonment creates no account');
DB::table('pending_registrations')->where('id',$abandoned)->update(['expires_at'=>now()->subSecond()]);
App\Services\PendingRegistration::purge();check(!DB::table('pending_registrations')->where('id',$abandoned)->exists(),'Expired temporary registration cleaned up');
echo "PASS: {$count} validation and pending registration checks. SQLite only.\n";
