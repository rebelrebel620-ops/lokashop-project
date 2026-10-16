<?php
require __DIR__.'/revision_checks.php';
use Illuminate\Support\Facades\{DB,Mail,Auth};
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\{PublicCourierController,CompanyRegistrationController,ReviewController};
$before=$n;
$mailer=new class {public array $codes=[];public function raw($text,$callback){if(preg_match('/code is ([0-9]{6})/',$text,$m))$this->codes[]=$m[1];$callback(new class {public function to($e){return $this;}public function subject($s){return $this;}});}};Mail::swap($mailer);
function signupRequest(array $data){$tmp=tempnam(sys_get_temp_dir(),'courier-pdf');file_put_contents($tmp,"%PDF-1.4\nCourier test document");return requestFor($data,['id_document'=>new UploadedFile($tmp,'id.pdf','application/pdf',null,true),'permit_document'=>new UploadedFile($tmp,'permit.pdf','application/pdf',null,true)]);}
$valid=['courier_type'=>'rider','first_name'=>'Juan','last_name'=>'Rafael','email'=>'publicrider@example.test','phone'=>'09179990001','date_of_birth'=>'2000-01-01','region'=>'NCR','province'=>'Metro Manila','city'=>'Quezon City','barangay'=>'Commonwealth','street'=>'12 Test Street','postal_code'=>'1121','vehicle'=>'motorcycle','license_number'=>'A01-22-123456','license_expires_at'=>'2030-01-01','plate_number'=>'ABC1234','password'=>'Courier@2026','password_confirmation'=>'Courier@2026','terms'=>1];
Auth::logout();$controller=new PublicCourierController();
invalid(fn()=>$controller->register(signupRequest(array_replace($valid,['vehicle'=>'6-wheel truck']))),'vehicle','Public rider cannot register truck');
invalid(fn()=>$controller->register(signupRequest(array_replace($valid,['city'=>'Santa Cruz','province'=>'Laguna','region'=>'CALABARZON','barangay'=>'Pagsawitan']))),'city','Public courier needs an approved center in own municipality');
foreach(['rider','driver'] as $i=>$type){Auth::logout();$data=array_replace($valid,['courier_type'=>$type,'email'=>'public'.$type.'@example.test','phone'=>'0917999000'.($i+1),'license_number'=>'A01-22-12345'.($i+6),'plate_number'=>'PUBLIC'.($i+1),'vehicle'=>$type==='rider'?'motorcycle':'6-wheel truck']);$r=signupRequest($data);$controller->register($r);$pendingId=$r->session()->get('company_pending_registration_id');$u=App\Services\PendingRegistration::applicant($pendingId);ok(!DB::table('users')->where('email',$data['email'])->exists(),'Public '.$type.' has no account before verification');ok($u->courier_type===$type&&$u->company_id===$company,'Pending courier has correct territory and type');
$r=requestFor(['otp'=>end($mailer->codes)]);$r->session()->put('company_pending_registration_id',$pendingId);(new CompanyRegistrationController())->verify($r);$u=DB::table('users')->where('email',$data['email'])->first();ok($u&&$u->approval_status==='pending'&&$u->email_verified_at,'Verified public '.$type.' awaits approval');
loginAs($adminId);(new ReviewController())->approve(requestFor(['decision'=>'approved']),$u->id);ok(DB::table('users')->find($u->id)->approval_status==='approved','Logistics admin can approve public '.$type);}
Auth::logout();requestFor();$html=$controller->form()->render();ok(str_contains($html,'name="courier_type"')&&str_contains($html,'/register/courier'),'Public form renders rider/driver selection');
$routes=$app['router']->getRoutes();$route=$routes->match(Illuminate\Http\Request::create('/register/courier','POST'));ok(str_contains($route->getActionName(),'PublicCourierController@register'),'Public courier submission route exists');
echo 'PASS: '.($n-$before)." public courier registration checks.\n";
