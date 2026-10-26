<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CourierSetupController extends Controller {
 public function team(Request $r){abort_unless(auth()->user()->role==='sorting_center',403);$v=$r->validate(['type'=>'nullable|in:rider,driver','q'=>'nullable|string|max:100']);$type=$v['type']??'rider';$base=DB::table('users')->where('company_id',auth()->id())->where('role','rider');$members=(clone $base)->where('courier_type',$type)->when(!empty($v['q']),fn($q)=>$q->where(fn($s)=>$s->where('name','like','%'.$v['q'].'%')->orWhere('email','like','%'.$v['q'].'%')))->orderBy('name')->paginate(12)->withQueryString();return view('center_team',['links'=>app(CommonController::class)->links(),'type'=>$type,'members'=>$members,'riderCount'=>(clone $base)->where('courier_type','rider')->count(),'driverCount'=>(clone $base)->where('courier_type','driver')->count()]);}
 public function settings(Request $r,string $module){abort_unless(auth()->user()->role==='rider'&&in_array($module,['availability','vehicle']),403);$u=auth()->user();return view('courier_settings',['links'=>app(CommonController::class)->links(),'user'=>$u,'module'=>$module,'isDriver'=>$u->courier_type==='driver','jobs'=>\App\Services\BulkTrips::workload($u->id,['accepted'])]);}
}
