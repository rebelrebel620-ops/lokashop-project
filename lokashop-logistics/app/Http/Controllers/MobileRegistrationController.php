<?php
namespace App\Http\Controllers;
use App\Services\{PendingRegistration,Registration,Support};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{Crypt,DB,Storage};
class MobileRegistrationController extends Controller {
 public static function started(int $id){return response()->json(['registration_token'=>Crypt::encryptString('mobile-registration:'.$id),'message'=>'Check your email for the verification code.']);}
 private function id(Request $r): int {$v=$r->validate(['registration_token'=>'required|string|max:2048']);try{$plain=Crypt::decryptString($v['registration_token']);}catch(\Throwable $e){abort(403,'Invalid registration session.');}abort_unless(preg_match('/^mobile-registration:([0-9]+)$/',$plain,$m),403);$id=(int)$m[1];$u=PendingRegistration::applicant($id);abort_unless(in_array($u->role,['sorting_center','rider']),403);return $id;}
 public function register(Request $r){$v=$r->validate(['account_type'=>'required|in:sorting_center,courier']);return $v['account_type']==='courier'?app(PublicCourierController::class)->register($r):app(CompanyRegistrationController::class)->register($r);}
 public function verify(Request $r){$id=$this->id($r);$v=$r->validate(['otp'=>['required','regex:/^[0-9]{6}$/']]);$error=PendingRegistration::verify($id,$v['otp']);if($error)Support::fail('otp',$error);return response()->json(['message'=>'Email verified. Your application is awaiting approval. You can log in after approval.']);}
 public function resend(Request $r){$id=$this->id($r);$u=PendingRegistration::applicant($id);Registration::issueOtp($id,$u->email);return response()->json(['message'=>'New verification code sent.']);}
 public function cancel(Request $r){$id=$this->id($r);DB::transaction(function()use($id){$p=DB::table('pending_registrations')->where('id',$id)->lockForUpdate()->first();if(!$p)return;$data=json_decode(Crypt::decryptString($p->payload),true);foreach($data['related'] as $e)if($e['table']==='registration_documents')Storage::disk('local')->delete($e['row']['path']);DB::table('pending_registrations')->where('id',$id)->delete();});return response()->json(['message'=>'Registration cancelled.']);}
}
