<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
class Support {
 public static function email(int $id,string $subject,string $body):void {
  DB::afterCommit(function()use($id,$subject,$body){$u=DB::table('users')->find($id);if(!$u)return;try{\Illuminate\Support\Facades\Mail::raw($body,fn($m)=>$m->to($u->email)->subject($subject));}catch(\Throwable $e){\Illuminate\Support\Facades\Log::warning('Account notification email could not be sent.',['user_id'=>$id]);}});
 }
 public static function fail(string $field,string $message): never {throw ValidationException::withMessages([$field=>$message]);}
 public static function audit(string $action,string $entity,int $id,$old,$new,?string $reason=null): void {
  DB::table('audit_logs')->insert(['actor_id'=>auth()->id(),'action'=>$action,'entity'=>$entity,'entity_id'=>$id,'old_value'=>json_encode($old),'new_value'=>json_encode($new),'reason'=>$reason,'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function notify(array $ids,string $body): void {
  foreach(array_unique(array_filter($ids)) as $id)DB::table('notifications')->insert(['user_id'=>$id,'body'=>$body,'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function once(Request $r,string $action,callable $fn) {
  $key=$r->header('Idempotency-Key')??$r->input('_request_key');
  if(!$key || !preg_match('/^[a-zA-Z0-9-]{16,64}$/',$key))self::fail('_request_key','Reload the form and submit again. An idempotency key is required.');
  return DB::transaction(function()use($key,$action,$fn){
   // A user-row lock serializes create requests, including requests for the same key.
   DB::table('users')->where('id',auth()->id())->lockForUpdate()->first();
   $saved=DB::table('request_keys')->where('key',$key)->first();
   if($saved){abort_unless($saved->user_id==auth()->id()&&$saved->action===$action,409);return json_decode($saved->result,true);}
   $result=$fn();DB::table('request_keys')->insert(['key'=>$key,'user_id'=>auth()->id(),'action'=>$action,'result'=>json_encode($result),'created_at'=>now(),'updated_at'=>now()]);return $result;
  });
 }
 public static function photos(Request $r,string $field='photos',int $min=0,int $max=5,string $disk='public'): array {
  $r->validate([$field=>['nullable','array','min:'.$min,'max:'.$max],$field.'.*'=>['file','mimes:jpg,jpeg,png','extensions:jpg,jpeg,png','max:5120']]);
  $files=$r->file($field,[]);if(count($files)<$min)self::fail($field,"Upload at least {$min} image(s).");
  return array_map(fn($f)=>$f->store('uploads',$disk),$files);
 }
 public static function imageUrl(?string $path): ?string{return $path?url('/media/'.$path):null;}
 public static function money($n): float{return round((float)$n,2,PHP_ROUND_HALF_UP);}
 public static function ownsOrder(int $id){$o=DB::table('orders')->find($id);abort_unless($o,404);$u=auth()->user();abort_unless($o->buyer_id==$u->id||$o->seller_id==$u->id||$u->role==='admin',403);return $o;}
}
