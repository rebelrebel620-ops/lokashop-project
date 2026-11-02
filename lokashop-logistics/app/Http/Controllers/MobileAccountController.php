<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\{ApiToken,Cart,Registration,Support};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{Auth,Cache,DB,Hash,Mail,Storage};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
class MobileAccountController extends Controller {
 private function user(Request $r): User {
  $u=ApiToken::user($r->bearerToken());abort_unless(MobileWorkspaceController::allowed($u),401,'Please log in again.');
  Auth::setUser($u);$r->setUserResolver(fn()=>$u);return $u;
 }
 public function profile(Request $r){$u=$this->user($r);Cart::profile($r);return app(MobileWorkspaceController::class)->me($r);}
 public function photo(Request $r){$u=$this->user($r);abort_unless($u->profile_photo&&Storage::disk('public')->exists($u->profile_photo),404);return response()->file(Storage::disk('public')->path($u->profile_photo),['Cache-Control'=>'private, no-store']);}
 public function password(Request $r){$u=$this->user($r);$v=$r->validate(['current_password'=>'required|string','password'=>['required','confirmed',Password::min(8)->mixedCase()->numbers()->symbols()]]);
  if(!Hash::check($v['current_password'],$u->password))Support::fail('current_password','Current password is incorrect.');
  DB::table('users')->where('id',$u->id)->update(['password'=>Hash::make($v['password']),'remember_token'=>null,'updated_at'=>now()]);
  Cache::forget('mobile-contact:'.$u->id);return response()->json(['message'=>'Password updated. Please log in again.']);
 }
 public function contact(Request $r){$u=$this->user($r);$r->merge(['email'=>mb_strtolower(trim((string)$r->email)),'phone'=>Registration::normalizePhone((string)$r->phone)]);
  $v=$r->validate(['kind'=>'required|in:email,phone','password'=>'required|string','email'=>['nullable','email',Rule::unique('users','email')->ignore($u->id)],'phone'=>['nullable','regex:/^09[0-9]{9}$/',Rule::unique('users','phone_normalized')->ignore($u->id)]]);
  if(!Hash::check($v['password'],$u->password))Support::fail('password','Current password is incorrect.');
  $kind=$v['kind'];$value=$v[$kind]??'';if(!$value)Support::fail($kind,'Enter your new '.$kind.'.');
  if($value===$u->$kind)Support::fail($kind,'Enter a different '.$kind.'.');
  $key='mobile-contact:'.$u->id;
  return Cache::lock($key.':lock',10)->block(3,function()use($key,$kind,$value,$u){$old=Cache::get($key);if($old&&$old['sent']>time()-60)Support::fail('otp','Wait one minute before requesting another code.');
   $code=(string)random_int(100000,999999);Mail::raw("Your LokaShop contact change code is {$code}. It expires in 5 minutes.",fn($m)=>$m->to($kind==='email'?$value:$u->email)->subject('Verify LokaShop contact change'));
   Cache::put($key,['kind'=>$kind,'value'=>$value,'hash'=>Hash::make($code),'attempts'=>0,'sent'=>time(),'stamp'=>hash('sha256',$u->password)],now()->addMinutes(5));
   return response()->json(['message'=>$kind==='email'?'Code sent to your new email.':'Code sent to your registered email to confirm the new phone number.']);});
 }
 public function verifyContact(Request $r){$u=$this->user($r);$v=$r->validate(['otp'=>['required','regex:/^[0-9]{6}$/']]);$key='mobile-contact:'.$u->id;
  return Cache::lock($key.':lock',10)->block(3,function()use($key,$u,$v,$r){$p=Cache::get($key);if(!$p||$p['attempts']>=5||$p['stamp']!==hash('sha256',$u->password))Support::fail('otp','Code expired or locked. Request a new code.');
   if(!Hash::check($v['otp'],$p['hash'])){$p['attempts']++;Cache::put($key,$p,now()->addSeconds(max(1,$p['sent']+300-time())));Support::fail('otp','Incorrect verification code.');}
   $field=$p['kind']==='phone'?'phone_normalized':'email';if(DB::table('users')->where($field,$p['value'])->where('id','!=',$u->id)->exists())Support::fail($field,'This contact is already in use.');
   $data=[$p['kind']=>$p['value'],'updated_at'=>now()];if($p['kind']==='phone')$data['phone_normalized']=$p['value'];else $data['email_verified_at']=now();
   try{DB::table('users')->where('id',$u->id)->update($data);}catch(\Illuminate\Database\UniqueConstraintViolationException $e){Support::fail($field,'This contact is already in use.');}
   Cache::forget($key);return app(MobileWorkspaceController::class)->me($r);});
 }
}
