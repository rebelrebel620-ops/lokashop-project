<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Conversations
{
    public static function page(Request $request): array
    {
        $request->validate(['with'=>'nullable|integer|min:1']);
        $me=auth()->user();
        $roles=match($me->role){'buyer'=>['seller','admin'],'seller'=>['buyer','admin'],'admin'=>['buyer','seller'],'rider'=>['sorting_center'],'sorting_center'=>['rider','logistics_admin'],'logistics_admin'=>['sorting_center'],default=>[]};
        $allowed=DB::table('users')->where('id','!=',$me->id)->where('active',1)->where('account_status','active')->where('approval_status','approved')->whereIn('role',$roles);
        if($me->role==='rider')$allowed->where('id',$me->company_id);
        if($me->role==='sorting_center')$allowed->where(fn($q)=>$q->where('role','logistics_admin')->orWhere('company_id',$me->id));
        $allowedIds=$allowed->pluck('id');
        $history=DB::table('messages')->where(fn($q)=>$q->where('sender_id',$me->id)->orWhere('recipient_id',$me->id))->get(['sender_id','recipient_id']);
        $historicIds=$history->map(fn($m)=>$m->sender_id==$me->id?$m->recipient_id:$m->sender_id);
        $users=DB::table('users')->whereIn('id',$allowedIds->merge($historicIds)->unique())->orderBy('name')->get(['id','name','role','courier_type','profile_photo']);
        $recent=DB::table('messages')->where(fn($q)=>$q->where('sender_id',$me->id)->orWhere('recipient_id',$me->id))->orderByDesc('id')->limit(300)->get();
        foreach($users as $user){$user->can_message=$allowedIds->contains($user->id);$user->last_message=$recent->first(fn($m)=>$m->sender_id==$user->id||$m->recipient_id==$user->id);}
        $users=$users->sortByDesc(fn($u)=>$u->last_message->id??0)->values();
        $selected=$request->filled('with')?$users->firstWhere('id',(int)$request->input('with')):$users->first();
        if($request->filled('with'))abort_unless($selected,404);
        $messages=collect();
        if($selected){$messages=DB::table('messages')->where(fn($q)=>$q->where('sender_id',$me->id)->where('recipient_id',$selected->id))->orWhere(fn($q)=>$q->where('sender_id',$selected->id)->where('recipient_id',$me->id))->orderByDesc('id')->limit(100)->get()->reverse()->values();}
        return compact('users','selected','messages');
    }
}
