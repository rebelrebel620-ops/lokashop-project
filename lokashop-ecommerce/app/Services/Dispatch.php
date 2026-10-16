<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Dispatch {
 public static function accept(int $id,int $rider):void {
  DB::transaction(function()use($id,$rider){
   $u=DB::table('users')->where('id',$rider)->lockForUpdate()->first();
   $a=DB::table('assignments')->where('id',$id)->where('rider_id',$rider)->lockForUpdate()->first();abort_unless($a,403);
   Municipalities::courier($u,DB::table('parcels')->find($a->parcel_id),$a->kind);if($a->status==='accepted')return;
   if($a->status!=='offered'||($a->expires_at&&now()->gte($a->expires_at)))Support::fail('assignment','This offer has expired or is no longer available.');
   if(!$u->active||!$u->online||$u->approval_status!=='approved'||!$u->last_seen_at||now()->diffInMinutes($u->last_seen_at,true)>=30)Support::fail('online','Go Online to accept jobs.');
   $center=DB::table('parcels')->where('id',$a->parcel_id)->value('center_id');$company=DB::table('sorting_centers')->where('id',$center)->value('user_id');abort_unless($u->company_id==$company,403);
   $jobs=DB::table('assignments')->where('rider_id',$rider)->where('status','accepted')->count();if($jobs>=3)Support::fail('assignment','You already have 3 active jobs.');
   DB::table('assignments')->where('id',$id)->update(['status'=>'accepted','updated_at'=>now()]);
  });
 }
 public static function offer(int $parcelId,int $company,?int $rider=null,string $kind='delivery'):void {
  DB::transaction(function()use($parcelId,$company,$rider,$kind){
   $p=DB::table('parcels')->where('id',$parcelId)->lockForUpdate()->first();abort_unless($p,404);
   if($kind==='delivery'&&!in_array($p->status,['SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER']))Support::fail('parcel','Parcel is not ready for delivery assignment.');
   $cq=DB::table('users')->where('role','rider')->where('courier_type',$kind==='transfer'?'driver':'rider')->where('company_id',$company)->where('active',1)->where('approval_status','approved')->where('online',1)->where('last_seen_at','>',now()->subMinutes(30));
   if($rider)$cq->where('id',$rider);
   // Prefer nearest rider when both locations are supplied; otherwise use workload.
   $tried=DB::table('assignments')->where('parcel_id',$p->id)->where('kind',$kind)->whereIn('status',['declined','expired'])->pluck('rider_id');if(!$rider)$cq->whereNotIn('id',$tried);$candidates=$cq->orderBy('id')->lockForUpdate()->get();$chosen=null;$least=INF;$origin=DB::table('users')->find($company);
   foreach($candidates as $u){try{Municipalities::courier($u,$p,$kind);}catch(\Illuminate\Validation\ValidationException $e){continue;}$n=DB::table('assignments')->where('rider_id',$u->id)->whereIn('status',['offered','accepted'])->count();$distance=($origin->latitude!==null&&$origin->longitude!==null&&$u->latitude!==null&&$u->longitude!==null)?self::distance($origin->latitude,$origin->longitude,$u->latitude,$u->longitude):1000000+$n;if($n<3&&$distance<$least){$chosen=$u;$least=$distance;}}
   if(!$chosen)Support::fail('rider_id','No Online rider with capacity is available in this company.');
   DB::table('assignments')->where('parcel_id',$p->id)->where('kind',$kind)->whereIn('status',['offered','accepted','requested'])->update(['status'=>'reassigned','updated_at'=>now()]);
   DB::table('assignments')->insert(['parcel_id'=>$p->id,'rider_id'=>$chosen->id,'kind'=>$kind,'status'=>'offered','expires_at'=>now()->addSeconds(60),'earnings'=>$kind==='pickup'?30:50,'created_at'=>now(),'updated_at'=>now()]);
   if($kind==='delivery'&&$p->status!=='ASSIGNED_TO_RIDER')Workflow::move($p->id,'ASSIGNED_TO_RIDER',$company,null,null,$p->center_id);
   Support::notify([$chosen->id],"New {$kind} offer for parcel #{$p->id}; accept within 60 seconds.");
  });
 }
 public static function distance($a,$b,$c,$d):float{$x=sin(deg2rad($c-$a)/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin(deg2rad($d-$b)/2)**2;return 6371*2*atan2(sqrt($x),sqrt(max(0,1-$x)));}
}
