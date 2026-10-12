<?php
namespace App\Http\Controllers;
use App\Services\Registration;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationController extends Controller {
    private function applicant(Request $r) {
        $u=DB::table('users')->find($r->session()->get('registration_user_id'));
        abort_unless($u && in_array($u->role,['buyer','seller'],true) && $u->approval_status==='unverified',403);
        return $u;
    }
    public function form(Request $r){return view('verify',['email'=>$this->applicant($r)->email]);}
    public function verify(Request $r){
        $v=$r->validate(['otp'=>['required','regex:/^[0-9]{6}$/']]);$u=$this->applicant($r);
        $error=DB::transaction(function()use($u,$v){
            $current=DB::table('users')->where('id',$u->id)->lockForUpdate()->first();
            if(!$current || $current->approval_status!=='unverified') return 'This verification request has already been used.';
            $otp=DB::table('registration_otps')->where('user_id',$u->id)->lockForUpdate()->first();
            if(!$otp || now()->greaterThanOrEqualTo($otp->expires_at)) return 'This code has expired. Request a new code.';
            if($otp->attempts>=5) return 'Too many incorrect attempts. Request a new code.';
            if(!Hash::check($v['otp'],$otp->code_hash)) {
                DB::table('registration_otps')->where('user_id',$u->id)->increment('attempts');
                return 'Incorrect verification code.';
            }
            DB::table('users')->where('id',$u->id)->where('approval_status','unverified')->update([
                'email_verified_at'=>now(),'approval_status'=>$u->role==='buyer'?'approved':'pending','updated_at'=>now()]);
            DB::table('registration_otps')->where('user_id',$u->id)->delete(); return null;
        });
        if($error)return back()->withErrors(['otp'=>$error]);
        $r->session()->forget('registration_user_id');
        return redirect('/login')->with('success',$u->role==='buyer'?'Email verified. You can now log in.':'Email verified. Your seller application is pending admin approval.');
    }
    public function resend(Request $r){
        $u=$this->applicant($r);
        $sent=DB::transaction(function()use($u){
            $locked=DB::table('users')->where('id',$u->id)->lockForUpdate()->first();
            if($locked->approval_status!=='unverified')return false;
            $otp=DB::table('registration_otps')->where('user_id',$u->id)->first();
            if($otp && now()->subMinute()->lessThan($otp->sent_at)) return false;
            Registration::issueOtp($u->id,$u->email);return true;
        });
        return $sent?back()->with('success','A new code was sent. The previous code no longer works.'):
            back()->withErrors(['otp'=>'Please wait one minute before requesting another code.']);
    }
}
