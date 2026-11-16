<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Mail};
use Illuminate\Validation\Rules\Password;
use App\Services\{Support,Registration};
class AccountSecurityController extends Controller {
 private function done(Request $r,string $message){return $r->expectsJson()?response()->json(['message'=>$message]):back()->with('success',$message);}
 public function password(Request $r){
  $v=$r->validate(['current_password'=>'required|string','password'=>['required','confirmed',Password::min(8)->mixedCase()->numbers()->symbols()]]);
  if(!Hash::check($v['current_password'],auth()->user()->password))Support::fail('current_password','Current password is incorrect.');
  DB::table('users')->where('id',auth()->id())->update(['password'=>Hash::make($v['password']),'remember_token'=>null,'api_token_expires_at'=>null,'updated_at'=>now()]);
  $r->session()->forget('contact_change');$r->session()->regenerate();Support::notify([auth()->id()],'Your account password was changed.');return $this->done($r,'Password changed; mobile tokens revoked.');
 }
 public function contactRequest(Request $r){
  $v=$r->validate(['password'=>'required|string','email'=>'nullable|email|max:190','phone'=>'nullable|string|max:40']);$u=auth()->user();
  if(!Hash::check($v['password'],$u->password))Support::fail('password','Password is incorrect.');
  $old=$r->session()->get('contact_change');if($old&&($old['sent_at']??0)>now()->timestamp-60)Support::fail('contact','Wait one minute before requesting another code.');
  $data=[];if(!empty($v['email'])){$email=mb_strtolower(trim($v['email']));if($email!==$u->email)$data['email']=$email;}
  if(!empty($v['phone'])){$phone=Registration::normalizePhone($v['phone']);if(!preg_match('/^09[0-9]{9}$/',$phone))Support::fail('phone','Enter a valid Philippine mobile number.');if($phone!==Registration::normalizePhone((string)$u->phone)){$data['phone']=$phone;$data['phone_normalized']=$phone;}}
  if(!$data)Support::fail('contact','Enter a different email or phone.');self::uniqueContact($data,(int)$u->id);
  $otp=(string)random_int(100000,999999);Mail::raw('Your account change verification code is '.$otp,fn($m)=>$m->to($data['email']??$u->email)->subject('Verify account change'));
  $r->session()->put('contact_change',['user_id'=>$u->id,'data'=>$data,'hash'=>Hash::make($otp),'password_stamp'=>hash('sha256',$u->password),'expires'=>now()->addMinutes(5)->timestamp,'sent_at'=>now()->timestamp,'attempts'=>0]);return $this->done($r,'Verification code sent by email.');
 }
 private static function uniqueContact(array $data,int $user):void {
  if(isset($data['email'])&&DB::table('users')->where('email',$data['email'])->where('id','!=',$user)->exists())Support::fail('email','Email already used.');
  if(isset($data['phone'])&&DB::table('users')->where(fn($q)=>$q->where('phone_normalized',$data['phone'])->orWhere('phone',$data['phone']))->where('id','!=',$user)->exists())Support::fail('phone','Mobile number already used.');
 }
 public function contactVerify(Request $r){
  $v=$r->validate(['otp'=>['required','regex:/^[0-9]{6}$/']]);$x=$r->session()->get('contact_change');$u=DB::table('users')->find(auth()->id());
  if(!$x||($x['user_id']??null)!=$u->id||$x['expires']<=now()->timestamp||$x['attempts']>=5||!hash_equals($x['password_stamp']??'',hash('sha256',$u->password)))Support::fail('otp','Expired or locked request. Request a new code.');
  if(!Hash::check($v['otp'],$x['hash'])){$x['attempts']++;$r->session()->put('contact_change',$x);Support::fail('otp','Incorrect verification code.');}
  try{DB::transaction(function()use($x,$u){DB::table('users')->where('id',$u->id)->lockForUpdate()->first();self::uniqueContact($x['data'],(int)$u->id);$data=$x['data']+['remember_token'=>null,'api_token_expires_at'=>null,'updated_at'=>now()];if(isset($data['email']))$data['email_verified_at']=now();DB::table('users')->where('id',$u->id)->update($data);});}catch(\Illuminate\Database\UniqueConstraintViolationException $e){Support::fail('contact','This contact is already in use. Request another change.');}
  $r->session()->forget('contact_change');$r->session()->regenerate();Support::notify([$u->id],'Your account contact details were updated.');return $this->done($r,'Contact updated.');
}
}
