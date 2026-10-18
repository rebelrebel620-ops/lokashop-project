<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsAdminController extends Controller
{
    private function accounts()
    {
        return DB::table('users')->whereIn('role', ['sorting_center', 'rider']);
    }

    public function approvals()
    {
        $users = $this->accounts()->where('approval_status', 'pending')->orderBy('id')->paginate(12)->withQueryString();
        return view('admin_approvals', [
            'links' => app(CommonController::class)->links(), 'users' => $users,
            'docs' => DB::table('registration_documents')->whereIn('user_id', $users->pluck('id'))->get()->groupBy('user_id'),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $values = $request->validate(['decision' => 'required|in:approved,rejected']);
        DB::transaction(function () use ($id, $values) {
            abort_unless($this->accounts()->where('id', $id)->where('approval_status', 'pending')
                ->update(['approval_status' => $values['decision'], 'updated_at' => now()]), 422);
            DB::table('notifications')->insert([
                'user_id' => $id, 'body' => 'Your registration is '.$values['decision'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Decision saved.');
    }

    public function document(int $id)
    {
        $document = DB::table('registration_documents as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.id', $id)->whereIn('u.role', ['sorting_center', 'rider'])->select('d.*')->first();
        abort_unless($document, 404);
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
