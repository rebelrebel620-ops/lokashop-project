<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsAdminController extends Controller
{
    public function commissions() {
        abort_unless(auth()->user()->role==='logistics_admin',403);
        $records=DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')->whereIn('o.status',['DELIVERED','COMPLETED'])->whereNull('o.refunded_at')->select('o.id','p.tracking_code','o.status','o.shipping_fee','o.shipping_commission',DB::raw('(o.shipping_fee-o.shipping_commission) as delivery_rider_earnings'))->orderByDesc('o.id')->paginate(30);
        return view('list',['title'=>'Shipping commission (20%) — successful deliveries','links'=>app(CommonController::class)->links(),'records'=>$records]);
    }

    private function accounts()
    {
        return DB::table('users')->whereIn('role', ['sorting_center', 'rider']);
    }

    private function applications() {
        $u=auth()->user();
        abort_unless(in_array($u->role,['logistics_admin','sorting_center']),403);
        $q=DB::table('users');
        if($u->role==='logistics_admin')return $q->where('role','sorting_center');
        $center=DB::table('sorting_centers')->where('user_id',$u->id)->first();
        return $q->where('role','rider')->where('company_id',$u->id)->whereIn('id',DB::table('addresses')->where('city',$center->city)->where('province',$center->province)->select('user_id'));
    }

    public function approvals()
    {
        $users = $this->applications()->where('approval_status', 'pending')->orderBy('id')->paginate(12)->withQueryString();
        return view('admin_approvals', [
            'links' => app(CommonController::class)->links(), 'users' => $users,
            'approvalBase' => auth()->user()->role==='sorting_center'?'/center':'/admin',
            'docs' => DB::table('registration_documents')->whereIn('user_id', $users->pluck('id'))->get()->groupBy('user_id'),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        return app(ReviewController::class)->approve($request,$id);
    }

    public function document(int $id)
    {
        $document = DB::table('registration_documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.id', $id)->whereIn('u.role', ['sorting_center', 'rider'])->select('d.*')->first();
        abort_unless($document, 404);
        $user=DB::table('users')->find($document->user_id);
        abort_unless(auth()->user()->role==='logistics_admin' ? $user->role==='sorting_center' : ($user->role==='rider' && $user->company_id==auth()->id() && \App\Services\Municipalities::matches(\App\Services\Municipalities::address($user->id),DB::table('sorting_centers')->where('user_id',auth()->id())->first())),403);
        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path));
    }

    public function users(Request $request)
    {
        $v=$request->validate(['search'=>'nullable|string|max:150','role'=>'nullable|in:sorting_center,rider,driver','state'=>'nullable|in:active,suspended,banned,archived','approval'=>'nullable|in:approved,pending,unverified,rejected,pending_activation']);
        $search=trim($v['search']??'');$role=$v['role']??'';$state=$v['state']??'';$approval=$v['approval']??'';
        $query=$this->accounts();
        if($search!=='')$query->where(fn($q)=>$q->where('name','like','%'.$search.'%')->orWhere('email','like','%'.$search.'%'));
        if($role==='sorting_center')$query->where('role','sorting_center');
        elseif(in_array($role,['rider','driver']))$query->where('role','rider')->where('courier_type',$role);
        if($state!=='')$query->where('account_status',$state);else $query->where('account_status','!=','archived');
        if($approval!=='')$query->where('approval_status',$approval);
        return view('admin_users',[
            'links'=>app(CommonController::class)->links(),
            'users'=>$query->select('id','name','email','role','approval_status','active','account_status','courier_type')->orderByDesc('id')->paginate(12)->withQueryString(),
            'search'=>$search,'role'=>$role,'state'=>$state,'approval'=>$approval,
        ]);
    }

    public function toggle(int $id)
    {
        \App\Services\Support::fail('status','Use the account status menu and provide a reason.');
        $user = $this->accounts()->where('id', $id)->first();
        abort_unless($user, 404);
        $this->accounts()->where('id', $id)->update(['active' => !$user->active, 'updated_at' => now()]);
        return back()->with('success', 'Account changed.');
    }
}
