<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsAdminController extends Controller
{
    public function commissions() {
        abort_unless(auth()->user()->role==='logistics_admin',403);
        $records=DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')->leftJoin('shipping_distributions as d','d.order_id','=','o.id')->whereIn('o.status',['DELIVERED','COMPLETED'])->select('o.id','p.tracking_code','o.status','o.shipping_fee','o.shipping_split_version','d.center_cents','d.driver_cents','d.rider_cents','d.unallocated_cents')->orderByDesc('o.id')->paginate(12);
        $records->getCollection()->transform(function($o){foreach(['center','driver','rider','unallocated'] as $role){$key=$role.'_cents';$o->{$role.'_share'}=$o->$key===null?'Legacy / pending':number_format($o->$key/100,2);unset($o->$key);}return $o;});
        return view('list',['title'=>'Shipping fee distribution — centers 50%, drivers 25%, delivery riders 25%','links'=>app(CommonController::class)->links(),'records'=>$records]);
    }

    private function accounts()
    {
        return DB::table('users')->whereIn('role', ['sorting_center', 'rider']);
    }

    private function applications()
    {
        $u=auth()->user();
        abort_unless(in_array($u->role,['logistics_admin','sorting_center']),403);
        $q=DB::table('users');
        if($u->role==='logistics_admin')return $q->where('role','sorting_center');
        $center=DB::table('sorting_centers')->where('user_id',$u->id)->first();
        abort_unless($center,403);
        $members=DB::table('users')->where('role','rider')->where('company_id',$u->id)->pluck('id');
        $addresses=DB::table('addresses')->whereIn('user_id',$members)->orderByDesc('is_default')->orderBy('id')->get()->unique('user_id');
        $ids=$addresses->filter(fn($a)=>\App\Services\Municipalities::matches($a,$center))->pluck('user_id');
        return $q->where('role','rider')->where('company_id',$u->id)->whereIn('id',$ids);
    }

    public function approvals()
    {
        $v=request()->validate(['status'=>'nullable|in:pending,approved,rejected','q'=>'nullable|string|max:150','courier_type'=>'nullable|in:rider,driver']);
        $status=$v['status']??'pending';$search=trim($v['q']??'');$type=$v['courier_type']??'';
        $isCenter=auth()->user()->role==='sorting_center';
        $base=$this->applications();
        $counts=(clone $base)->whereIn('approval_status',['pending','pending_activation','approved','rejected'])->selectRaw('approval_status, COUNT(*) as total')->groupBy('approval_status')->pluck('total','approval_status');
        $counts->put('pending',(int)$counts->get('pending',0)+(int)$counts->get('pending_activation',0));
        $query=(clone $base)->whereIn('approval_status',$status==='pending'?['pending','pending_activation']:[$status]);
        if($type!==''&&$isCenter)$query->where('courier_type',$type);
        if($search!==''){
            // Treat wildcard characters as literal input in both MySQL and SQLite.
            $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';
            $query->where(function($q)use($term,$search){
                foreach(['name','email','phone','plate_number','license_number'] as $field)$q->orWhereRaw($field." LIKE ? ESCAPE '!'",[$term]);
                $q->orWhereIn('id',DB::table('sorting_centers')->whereRaw("name LIKE ? ESCAPE '!'",[$term])->select('user_id'));
                if(ctype_digit(ltrim($search,'#')))$q->orWhere('id',(int)ltrim($search,'#'));
            });
        }
        $users=$query->orderByDesc('created_at')->orderByDesc('id')->paginate(12)->withQueryString();
        return view('admin_approvals',[
            'links'=>app(CommonController::class)->links(),'users'=>$users,
            'status'=>$status,'counts'=>$counts,'filters'=>['q'=>$search,'courier_type'=>$isCenter?$type:''],
            'isCenter'=>$isCenter,'approvalBase'=>$isCenter?'/center':'/admin',
            'addresses'=>DB::table('addresses')->whereIn('user_id',$users->pluck('id'))->orderByDesc('is_default')->orderBy('id')->get()->unique('user_id')->keyBy('user_id'),
            'centers'=>DB::table('sorting_centers')->whereIn('user_id',$users->pluck('id'))->get()->keyBy('user_id'),
            'docs'=>DB::table('registration_documents')->whereIn('user_id',$users->pluck('id'))->orderByDesc('id')->get()->groupBy('user_id'),
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
