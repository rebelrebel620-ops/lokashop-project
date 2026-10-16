<?php
namespace App\Http\Controllers;
use App\Services\Registration;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationController extends Controller {
    private function applicant(Request $r) {
        $u=\App\Services\PendingRegistration::applicant($r->session()->get('pending_registration_id'));
        abort_unless($u && in_array($u->role,['buyer','seller'],true) && $u->approval_status==='unverified',403);
        return $u;
    }
    public function form(Request $r){return view('verify',['email'=>$this->applicant($r)->email]);}
    public function verify(Request $r){
        $v=$r->validate(['otp'=>['required','regex:/^[0-9]{6}$/']]);$u=$this->applicant($r);
        $error=\App\Services\PendingRegistration::verify($u->id,$v['otp']);
        if($error)return back()->withErrors(['otp'=>$error]);
        $r->session()->forget('pending_registration_id');
        return redirect('/login')->with('success',$u->role==='buyer'?'Email verified. You can now log in.':'Email verified. Your seller application is pending admin approval.');
    }
    public function resend(Request $r){
        $u=$this->applicant($r);
        Registration::issueOtp($u->id,$u->email);$sent=true;
        return $sent?back()->with('success','A new code was sent. The previous code no longer works.'):
            back()->withErrors(['otp'=>'Please wait one minute before requesting another code.']);
    }
}
