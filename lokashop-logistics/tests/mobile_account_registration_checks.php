<?php
require __DIR__.'/workflow_checks.php';
use App\Http\Controllers\{MobileAccountController,MobileRegistrationController};
use App\Services\{ApiToken,Geography};
use App\Models\User;
use Illuminate\Support\Facades\{DB,Hash,Mail,Cache,Storage};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
$before=$n;config(['cache.default'=>'array']);
$mailer=new class {public array $codes=[];public array $to=[];public function raw($text,$callback){if(preg_match('/code is ([0-9]{6})/',$text,$m))$this->codes[]=$m[1];$owner=$this;$callback(new class($owner){public function __construct(private $owner){}public function to($email){$this->owner->to[]=$email;return $this;}public function subject($s){return $this;}});}};Mail::swap($mailer);
function mobileReq(array $data=[],array $files=[],?string $token=null){$r=requestFor($data,$files);if($token)$r->headers->set('Authorization','Bearer '.$token);return $r;}
function denied(callable $action,string $message){try{$action();throw new RuntimeException('FAIL: accepted '.$message);}catch(ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){ok(true,$message);}}
$account=new MobileAccountController();
foreach(['rider','driver','sorting_center'] as $index=>$role){
 $uid=user($role==='sorting_center'?'sorting_center':'rider','account-check-'.$index);DB::table('users')->where('id',$uid)->update(['courier_type'=>$role==='driver'?'driver':'rider','phone'=>'0918000000'.$index,'phone_normalized'=>'0918000000'.$index]);
 [$token]=ApiToken::issue(User::find($uid));
 denied(fn()=>$account->profile(mobileReq(['name'=>'New Name','phone'=>'09999999999'],[],$token)),'Direct phone edit blocked '.$role);
 $p=$account->profile(mobileReq(['name'=>'New Name','phone'=>'0918000000'.$index],['profile_photo'=>UploadedFile::fake()->image('photo.png',40,40)],$token))->getData(true);
 ok($p['rider']['name']==='New Name'&&str_starts_with($p['rider']['profile_photo_url'],'/api/mobile/photo?'),'Profile and photo URL '.$role);
 ok($account->photo(mobileReq([],[],$token)) instanceof Symfony\Component\HttpFoundation\BinaryFileResponse,'Authenticated photo serves '.$role);
 denied(fn()=>$account->photo(mobileReq()),'Unauthenticated photo blocked');
 denied(fn()=>$account->contact(mobileReq(['kind'=>'email','email'=>'new'.$index.'@example.test','password'=>'wrong'],[],$token)),'Wrong password contact blocked');
 $account->contact(mobileReq(['kind'=>'email','email'=>'new'.$index.'@example.test','password'=>'Test@1234'],[],$token));$code=end($mailer->codes);
 ok(DB::table('users')->find($uid)->email!=='new'.$index.'@example.test','Email unchanged before verification');
 denied(fn()=>$account->verifyContact(mobileReq(['otp'=>'000000'],[],$token)),'Wrong OTP blocked');
 $p=$account->verifyContact(mobileReq(['otp'=>$code],[],$token))->getData(true);ok($p['rider']['email']==='new'.$index.'@example.test','Email changed after OTP '.$role);
 denied(fn()=>$account->verifyContact(mobileReq(['otp'=>$code],[],$token)),'OTP cannot be reused');
 $phone='0919000000'.$index;$account->contact(mobileReq(['kind'=>'phone','phone'=>$phone,'password'=>'Test@1234'],[],$token));$code=end($mailer->codes);
 ok(end($mailer->to)==='new'.$index.'@example.test','Phone verification sent to registered email');
 $p=$account->verifyContact(mobileReq(['otp'=>$code],[],$token))->getData(true);ok($p['rider']['phone']===$phone&&DB::table('users')->find($uid)->phone_normalized===$phone,'Phone saved '.$role);
 denied(fn()=>$account->password(mobileReq(['current_password'=>'wrong','password'=>'Updated@1234','password_confirmation'=>'Updated@1234'],[],$token)),'Wrong current password blocked');
 $account->password(mobileReq(['current_password'=>'Test@1234','password'=>'Updated@1234','password_confirmation'=>'Updated@1234'],[],$token));ok(Hash::check('Updated@1234',DB::table('users')->find($uid)->password)&&ApiToken::user($token)===null,'Password saved and old token revoked '.$role);
}
$reg=new MobileRegistrationController();$valid=['account_type'=>'courier','courier_type'=>'rider','first_name'=>'Native','last_name'=>'Applicant','email'=>'native-rider@example.test','phone'=>'09200000000','date_of_birth'=>'2000-01-01','region'=>'NCR','province'=>'Metro Manila','city'=>'Quezon City','barangay'=>'Commonwealth','street'=>'12 Sample Street','postal_code'=>'1121','vehicle'=>'motorcycle','license_number'=>'N01-23-123456','license_expires_at'=>'2030-01-01','plate_number'=>'NAT123','password'=>'Native@1234','password_confirmation'=>'Native@1234','terms'=>'1'];
foreach(['rider','driver'] as $i=>$type){$data=array_replace($valid,['courier_type'=>$type,'email'=>'native-'.$type.'@example.test','phone'=>'0920000000'.$i,'vehicle'=>$type==='rider'?'motorcycle':'4-wheel truck','license_number'=>'N01-23-12345'.$i,'plate_number'=>'NAT12'.$i]);$count=DB::table('users')->count();$r=mobileReq($data,['id_document'=>UploadedFile::fake()->image('id.png'),'permit_document'=>UploadedFile::fake()->image('permit.png')]);$d=$reg->register($r)->getData(true);$code=end($mailer->codes);$t=$d['registration_token'];
 ok(DB::table('users')->count()===$count&&strlen($t)>40,'Native '.$type.' registration remains pending');
 denied(fn()=>$reg->verify(mobileReq(['registration_token'=>$t,'otp'=>'000000'])),'Wrong registration OTP blocked');
 $reg->verify(mobileReq(['registration_token'=>$t,'otp'=>$code]));$u=DB::table('users')->where('email',$data['email'])->first();ok($u&&$u->courier_type===$type&&$u->approval_status==='pending'&&$u->company_id==$company,'Verified '.$type.' awaits correct center approval');
 denied(fn()=>$reg->verify(mobileReq(['registration_token'=>$t,'otp'=>$code])),'Registration token cannot be replayed');
}
$geo=Geography::data();$province=collect($geo['provinces'])->firstWhere('name','Laguna');$city=collect($geo['cities-municipalities'])->firstWhere('provinceCode',$province['code']);$barangay=collect($geo['barangays'])->first(fn($b)=>($b['cityCode']?:$b['municipalityCode'])===$city['code']);$region=collect($geo['regions'])->firstWhere('code',$province['regionCode']);
$data=array_replace($valid,['account_type'=>'sorting_center','email'=>'native-center@example.test','phone'=>'09200000002','business_name'=>'Native Logistics Center','sec_number'=>'CS123456789','tin'=>'123-456-789-012','region'=>$region['name'],'province'=>$province['name'],'city'=>$city['name'],'barangay'=>$barangay['name']]);
$docs=fn()=>['id_document'=>UploadedFile::fake()->image('id.png'),'sec_document'=>UploadedFile::fake()->image('sec.png'),'bir_document'=>UploadedFile::fake()->image('bir.png')];
$d=$reg->register(mobileReq($data,$docs()))->getData(true);$t=$d['registration_token'];$code=end($mailer->codes);ok(!DB::table('users')->where('email',$data['email'])->exists(),'Center account absent before OTP');
$reg->verify(mobileReq(['registration_token'=>$t,'otp'=>$code]));$u=DB::table('users')->where('email',$data['email'])->first();ok($u&&$u->role==='sorting_center'&&$u->approval_status==='pending','Native center verification awaits admin approval');
// Abandon a courier registration; no user or uploaded document remains.
$data=array_replace($valid,['email'=>'abandon-native@example.test','phone'=>'09200000003','license_number'=>'N01-23-999999','plate_number'=>'ABN123']);$d=$reg->register(mobileReq($data,['id_document'=>UploadedFile::fake()->image('id.png'),'permit_document'=>UploadedFile::fake()->image('permit.png')]))->getData(true);
$pending=DB::table('pending_registrations')->where('email',$data['email'])->first();$payload=json_decode(Illuminate\Support\Facades\Crypt::decryptString($pending->payload),true);$paths=collect($payload['related'])->where('table','registration_documents')->pluck('row.path');
$reg->cancel(mobileReq(['registration_token'=>$d['registration_token']]));ok(!DB::table('pending_registrations')->where('id',$pending->id)->exists()&&!DB::table('users')->where('email',$data['email'])->exists(),'Cancel removes pending registration without creating account');ok($paths->every(fn($p)=>!Storage::disk('local')->exists($p)),'Cancel removes uploaded documents');
denied(fn()=>$reg->verify(mobileReq(['registration_token'=>'tampered','otp'=>'123456'])),'Tampered registration token rejected');
echo 'PASS: '.($n-$before)." native account and registration checks. SQLite and fake mail only.\n";
