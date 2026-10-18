<?php
namespace App\Http\Controllers;

use App\Services\{Conversations,Municipalities};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{DB,Storage};

class MobileRiderController extends Controller
{
    public function overview(Request $r)
    {
        \App\Services\PickupRequests::refresh();
        \App\Services\DeliveryOffers::refresh();
        $id=auth()->id();
        $q=DB::table('assignments')->where('rider_id',$id);
        return response()->json([
            'offered'=>(clone $q)->where('status','offered')->count(),
            'active'=>(clone $q)->where('status','accepted')->count(),
            'completed'=>(clone $q)->where('status','completed')->count(),
            'today_completed'=>(clone $q)->where('status','completed')->whereDate('updated_at',today())->count(),
            'earnings'=>(float)(clone $q)->where('status','completed')->sum('earnings'),
            'today_earnings'=>(float)(clone $q)->where('status','completed')->whereDate('updated_at',today())->sum('earnings'),
            'cod_unremitted'=>(float)DB::table('cod_collections')->where('rider_id',$id)->whereNull('remittance_id')->sum('amount'),
            'unread'=>DB::table('notifications')->where('user_id',$id)->whereNull('read_at')->count(),
            'center'=>DB::table('sorting_centers')->where('user_id',auth()->user()->company_id)->first(['name','city','province']),
            'address'=>Municipalities::address($id),
        ]);
    }

    public function cod(Request $r)
    {
        $q=DB::table('remittances')->where('rider_id',auth()->id());
        return response()->json([
            'unremitted'=>(float)DB::table('cod_collections')->where('rider_id',auth()->id())->whereNull('remittance_id')->sum('amount'),
            'open'=>(clone $q)->whereIn('status',['pending','short'])->first(),
            'records'=>$q->orderByDesc('id')->paginate(12),
        ]);
    }

    public function notifications(Request $r)
    {
        return response()->json(DB::table('notifications')->where('user_id',auth()->id())->orderByDesc('id')->paginate(12));
    }
    public function read(Request $r)
    {
        DB::table('notifications')->where('user_id',auth()->id())->whereNull('read_at')->update(['read_at'=>now()]);
        return response()->json(['message'=>'Notifications marked as read.']);
    }
    public function messages(Request $r)
    {
        $page=Conversations::page($r);
        foreach($page['messages'] as $m){
            $m->attachment_url=$m->attachment?url('/api/rider/messages/'.$m->id.'/attachment'):null;
            unset($m->attachment);
        }
        // Only expose the fields needed to list contacts, not raw message records.
        $page['users']=$page['users']->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'role'=>$u->role,'can_message'=>$u->can_message]);
        if($page['selected'])$page['selected']=['id'=>$page['selected']->id,'name'=>$page['selected']->name,'can_message'=>$page['selected']->can_message];
        return response()->json($page);
    }
    public function attachment(int $id)
    {
        $m=DB::table('messages')->where('id',$id)->where(fn($q)=>$q->where('sender_id',auth()->id())->orWhere('recipient_id',auth()->id()))->first();
        abort_unless($m&&$m->attachment&&Storage::disk('local')->exists($m->attachment),404);
        return response()->file(Storage::disk('local')->path($m->attachment),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function address(Request $r)
    {
        return app(AccountController::class)->address($r);
    }
    public function detail(Request $r,int $id)
    {
        $a=DB::table('assignments')->where('id',$id)->where('rider_id',auth()->id())->first();abort_unless($a,404);
        $p=DB::table('parcels')->find($a->parcel_id);abort_unless($p,404);
        return response()->json([
            'tracking_code'=>$p->tracking_code,'parcel_status'=>$p->status,
            'events'=>DB::table('status_history')->where('parcel_id',$p->id)->orderBy('id')->get(['to_status','reason','created_at']),
            'proof_url'=>$a->kind==='delivery'&&$a->status==='completed'&&$p->proof_path?url('/api/rider/assignments/'.$id.'/proof'):null,
        ]);
    }
    public function proof(int $id)
    {
        $a=DB::table('assignments')->where('id',$id)->where('rider_id',auth()->id())->where('kind','delivery')->where('status','completed')->first();abort_unless($a,404);
        $path=DB::table('parcels')->where('id',$a->parcel_id)->value('proof_path');abort_unless($path&&Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
