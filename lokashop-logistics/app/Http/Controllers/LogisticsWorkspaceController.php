<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticsWorkspaceController extends Controller
{
    private function admin(): void
    {
        abort_unless(auth()->user()->role === 'logistics_admin', 403);
    }

    public function territories(Request $request)
    {
        $this->admin();
        $v=$request->validate(['q'=>'nullable|string|max:150']);
        $query=DB::table('sorting_centers as c')->join('users as u','u.id','=','c.user_id');
        if(!empty($v['q']))$query->where(fn($q)=>$q->where('c.name','like','%'.$v['q'].'%')->orWhere('c.city','like','%'.$v['q'].'%')->orWhere('u.email','like','%'.$v['q'].'%'));
        return view('admin_territories',[
            'links'=>app(CommonController::class)->links(),
            'centers'=>$query->select('c.*','u.email','u.approval_status')->orderBy('c.name')->paginate(12)->withQueryString(),
            'totalCenters'=>DB::table('sorting_centers')->count(),
            'assignedCenters'=>DB::table('sorting_centers')->whereNotNull('municipality_key')->count(),
            'form'=>['label'=>'Save service territory','action'=>'/admin/territory','fields'=>['center_id:number','region','province','city','barangay','street','postal_code','reason:textarea']],
        ]);
    }

    public function legacy(Request $request)
    {
        $this->admin();
        $v=$request->validate(['q'=>'nullable|string|max:150']);
        $query=DB::table('users')->where('role','rider')->whereNull('company_id');
        if(!empty($v['q']))$query->where(fn($q)=>$q->where('name','like','%'.$v['q'].'%')->orWhere('email','like','%'.$v['q'].'%'));
        return view('admin_legacy',[
            'links'=>app(CommonController::class)->links(),
            'couriers'=>$query->select('id','name','email','courier_type','approval_status','active')->orderBy('name')->paginate(12)->withQueryString(),
            'form'=>['label'=>'Link courier to sorting center','action'=>'/ops/legacy','fields'=>['rider_id:number','company_id:number','courier_type:select:rider,driver','vehicle:select:motorcycle,tricycle,4-wheel truck,6-wheel truck,8-wheel truck,10-wheel truck','reason:textarea']],
        ]);
    }

    public function overview(Request $request)
    {
        $user=auth()->user();$admin=$user->role==='logistics_admin';
        abort_unless($admin || $user->role==='sorting_center',403);
        $center=$admin?null:DB::table('sorting_centers')->where('user_id',$user->id)->first();
        abort_unless($admin || $center,403,'No sorting center is linked to your account.');
        $v=$request->validate(['from'=>'nullable|date','to'=>'nullable|date'.($request->filled('from')?'|after_or_equal:from':'')]);
        $parcels=DB::table('parcels');
        if(!$admin)$parcels->where('center_id',$center->id);
        if(!empty($v['from']))$parcels->whereDate('created_at','>=',$v['from']);
        if(!empty($v['to']))$parcels->whereDate('created_at','<=',$v['to']);
        $couriers=DB::table('users')->where('role','rider');
        if(!$admin)$couriers->where('company_id',$user->id);
        $approved=(clone $couriers)->where('active',1)->where('account_status','active')->where('approval_status','approved');
        $statusCounts=(clone $parcels)->select('status',DB::raw('COUNT(*) as total'))->groupBy('status')->orderByDesc('total')->get();
        $pending=DB::table('users')->whereIn('role',['sorting_center','rider'])->where('approval_status','pending');
        if(!$admin)$pending->where('company_id',$user->id)->where('role','rider');
        $collections=DB::table('cod_collections')->whereNull('remittance_id');
        if(!$admin)$collections->where('company_id',$user->id);
        $pickup=DB::table('assignments')->where('kind','pickup')->where('status','requested');
        if(!$admin)$pickup->whereIn('rider_id',DB::table('users')->where('company_id',$user->id)->select('id'));
        $incoming=DB::table('parcels')->where('status','IN_TRANSIT_CENTER');
        if(!$admin)$incoming->where('destination_center_id',$center->id);
        $cards=[
            ['label'=>'Parcels in selected period','value'=>(clone $parcels)->count()],
            ['label'=>'Awaiting dispatch','value'=>(clone $parcels)->whereIn('status',['SORTED','DELIVERY_FAILED'])->count()],
            ['label'=>'Out for delivery','value'=>(clone $parcels)->where('status','OUT_FOR_DELIVERY')->count()],
            ['label'=>'Delivered / completed','value'=>(clone $parcels)->whereIn('status',['DELIVERED','COMPLETED'])->count()],
            ['label'=>'Approved riders','value'=>(clone $approved)->where('courier_type','rider')->count()],
            ['label'=>'Approved drivers','value'=>(clone $approved)->where('courier_type','driver')->count()],
            ['label'=>'Couriers online (last 30 minutes)','value'=>(clone $approved)->where('online',1)->where('last_seen_at','>=',now()->subMinutes(30))->count()],
            ['label'=>'Pending applications','value'=>$pending->count()],
        ];
        return view('logistics_overview',[
            'links'=>app(CommonController::class)->links(),'isAdmin'=>$admin,'center'=>$center,'cards'=>$cards,
            'statuses'=>$statusCounts,'recent'=>(clone $parcels)->orderByDesc('updated_at')->limit(12)->get(['id','tracking_code','status','updated_at']),
            'pickupRequests'=>$pickup->count(),'incomingTransfers'=>$incoming->count(),
            'unremittedCod'=>$collections->sum('amount'),
            'missingTerritories'=>$admin?DB::table('sorting_centers')->whereNull('municipality_key')->count():0,
            'legacyCouriers'=>$admin?DB::table('users')->where('role','rider')->whereNull('company_id')->count():0,
        ]);
    }
}
