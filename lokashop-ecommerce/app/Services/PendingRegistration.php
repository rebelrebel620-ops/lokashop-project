<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt,Hash,Mail};
use Illuminate\Validation\ValidationException;
class PendingRegistration {
 public static function user(array $data): int {
  $data=self::dates($data);
  return DB::table('pending_registrations')->insertGetId(['email'=>$data['email'],'payload'=>Crypt::encryptString(json_encode(['user'=>$data,'related'=>[]])),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function related(string $table,array $row): void {
  $row=self::dates($row);
  $p=DB::table('pending_registrations')->where('id',$row['user_id'])->lockForUpdate()->first();
  $data=json_decode(Crypt::decryptString($p->payload),true);$data['related'][]=['table'=>$table,'row'=>$row];
  DB::table('pending_registrations')->where('id',$p->id)->update(['payload'=>Crypt::encryptString(json_encode($data))]);
 }
 private static function dates(array $row): array {foreach($row as $key=>$value)if($value instanceof \DateTimeInterface)$row[$key]=$value->format('Y-m-d H:i:s');return $row;}
 public static function applicant($id) {
  $p=DB::table('pending_registrations')->find($id);abort_unless($p&&now()->lt($p->expires_at),403,'Registration expired. Please register again.');
  return (object)(json_decode(Crypt::decryptString($p->payload),true)['user']+['id'=>$p->id]);
 }
 public static function issueOtp(int $id,string $email): void {
  DB::transaction(function()use($id,$email){$p=DB::table('pending_registrations')->where('id',$id)->lockForUpdate()->first();abort_unless($p&&now()->lt($p->expires_at),403);
   if($p->sent_at&&now()->subMinute()->lt($p->sent_at))throw ValidationException::withMessages(['otp'=>'Wait one minute before requesting another code.']);
   $code=(string)random_int(100000,999999);DB::table('pending_registrations')->where('id',$id)->update(['code_hash'=>Hash::make($code),'attempts'=>0,'sent_at'=>now(),'code_expires_at'=>now()->addMinutes(5)]);
   Mail::raw("Your LokaShop verification code is {$code}. It expires in 5 minutes. Your registration is not complete until this code is verified. Your registration is not complete until this code is verified.",fn($m)=>$m->to($email)->subject('Verify your LokaShop email'));
  });
 }
 public static function resumeLegacy(int $userId): int {
  return DB::transaction(function()use($userId){$u=DB::table('users')->where('id',$userId)->lockForUpdate()->first();abort_unless($u&&$u->approval_status==='unverified',403);$data=(array)$u;unset($data['id']);$id=self::user($data);foreach(['addresses','registration_documents','seller_businesses','sorting_centers'] as $table){foreach(DB::table($table)->where('user_id',$userId)->get() as $row){$row=(array)$row;unset($row['id']);$row['user_id']=$id;self::related($table,$row);}DB::table($table)->where('user_id',$userId)->delete();}DB::table('registration_otps')->where('user_id',$userId)->delete();DB::table('users')->where('id',$userId)->delete();self::issueOtp($id,$u->email);return $id;});
 }
 public static function purge(): void {
  DB::table('pending_registrations')->where('expires_at','<=',now())->orderBy('id')->chunkById(100,function($rows){foreach($rows as $p)DB::transaction(function()use($p){$locked=DB::table('pending_registrations')->where('id',$p->id)->lockForUpdate()->first();if(!$locked||now()->lt($locked->expires_at))return;$data=json_decode(Crypt::decryptString($locked->payload),true);foreach($data['related'] as $entry)if($entry['table']==='registration_documents')\Illuminate\Support\Facades\Storage::disk('local')->delete($entry['row']['path']);DB::table('pending_registrations')->where('id',$p->id)->delete();});});
 }
 public static function verify(int $id,string $code): ?string {
  try{return DB::transaction(function()use($id,$code){$p=DB::table('pending_registrations')->where('id',$id)->lockForUpdate()->first();
   if(!$p||now()->gte($p->expires_at))return 'Registration expired. Please register again.';
   if(!$p->code_hash||now()->gte($p->code_expires_at)||$p->attempts>=5)return 'Code expired or locked. Request a new code.';
   if(!Hash::check($code,$p->code_hash)){DB::table('pending_registrations')->where('id',$id)->increment('attempts');return 'Incorrect verification code.';}
   $data=json_decode(Crypt::decryptString($p->payload),true);$u=$data['user'];
   if(DB::table('users')->where('email',$u['email'])->exists())return 'This email is already registered.';
   foreach(['phone_normalized','tin','sec_number','license_number','plate_number'] as $field)if(!empty($u[$field])&&DB::table('users')->where($field,$u[$field])->exists())return 'These registration details are already in use. Please register again with your own details.';
   foreach($data['related'] as $entry){$row=$entry['row'];if(in_array($entry['table'],['sorting_centers','seller_businesses'])){if(DB::table($entry['table'])->whereRaw('LOWER(TRIM(name)) = ?',[mb_strtolower(trim($row['name']))])->exists())return 'This business name was already registered. Please register again.';if(!empty($row['municipality_key'])&&DB::table('sorting_centers')->where('municipality_key',$row['municipality_key'])->exists())return 'This municipality already has a sorting center.';}}
   if($u['role']==='rider'){ $center=DB::table('users')->where('id',$u['company_id'])->lockForUpdate()->first();if(!$center||!$center->active||$center->account_status!=='active'||$center->approval_status!=='approved')return 'The selected sorting center is unavailable. Please register again.'; }
   $u['email_verified_at']=now();$u['approval_status']=$u['role']==='buyer'?'approved':'pending';$u['created_at']=now();$u['updated_at']=now();
   $uid=DB::table('users')->insertGetId($u);
   foreach($data['related'] as $entry){$row=$entry['row'];$row['user_id']=$uid;DB::table($entry['table'])->insert($row);}
   DB::table('pending_registrations')->where('id',$id)->delete();
   DB::afterCommit(fn()=>Support::email($uid,'LokaShop email verified — account created','Your email is verified and your account has been created.'.($u['role']==='buyer'?' You can now log in.':' Your application requires administrator approval.')));
   return null;
  });}catch(\Illuminate\Database\UniqueConstraintViolationException $e){return 'These registration details were just used by another account. Please register again.';}
 }
}
