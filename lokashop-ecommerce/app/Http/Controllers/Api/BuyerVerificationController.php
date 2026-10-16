<?php
namespace App\Http\Controllers\Api;
use App\Services\Registration;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{Crypt,DB,Hash};
use Illuminate\Validation\ValidationException;

class BuyerVerificationController extends Controller {
 public static function token(int $id): string {
  return Crypt::encryptString(json_encode(['purpose'=>'buyer-pending-email-v2','id'=>$id,'expires'=>now()->addDay()->timestamp]));
 }
 private function applicant(Request $r) {
  $v=$r->validate(['verification_token'=>'required|string|max:4096']);
  try {$data=json_decode(Crypt::decryptString($v['verification_token']),true);}
  catch(\Throwable $e){$data=null;}
  abort_unless(is_array($data)&&($data['purpose']??null)==='buyer-pending-email-v2'&&($data['expires']??0)>now()->timestamp,403,'Verification session expired. Log in again to continue.');
  $u=\App\Services\PendingRegistration::applicant($data['id']??0);
  abort_unless($u&&$u->role==='buyer'&&$u->active&&$u->approval_status==='unverified'&&$u->verification_required,403,'This verification request is no longer available.');
  return $u;
 }
 public function verify(Request $r) {
  $v=$r->validate(['otp'=>['required','regex:/^[0-9]{6}$/']]);$u=$this->applicant($r);
  $error=\App\Services\PendingRegistration::verify($u->id,$v['otp']);
        if($error)throw ValidationException::withMessages(['otp'=>$error]);
  return response()->json(['message'=>'Email verified. You can now log in.']);
 }
 public function resend(Request $r) {
  $u=$this->applicant($r);
  Registration::issueOtp($u->id,$u->email);$sent=true;
        if(!$sent)return response()->json(['message'=>'Please wait one minute before requesting another code.','retry_after'=>60],429);
  return response()->json(['message'=>'A new code was sent. The previous code no longer works.','retry_after'=>60]);
 }
}
