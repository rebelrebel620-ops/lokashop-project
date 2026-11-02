<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
class CourierOverviewController extends Controller {
 public function index(){ $u=auth()->user();abort_unless($u->role==='rider',403);\App\Services\BulkTrips::refresh();\App\Services\PickupRequests::refresh();\App\Services\DeliveryOffers::refresh();$driver=$u->courier_type==='driver';$jobs=DB::table('assignments')->where('rider_id',$u->id);$trips=DB::table('driver_trips')->where('driver_id',$u->id);$counts=$driver?['New trip offers'=>(clone $trips)->where('status','offered')->count(),'Active trips'=>(clone $trips)->whereIn('status',['accepted','in_transit'])->count(),'Completed trips'=>(clone $trips)->where('status','completed')->count()]:['New requests'=>(clone $jobs)->whereNull('trip_id')->where('status','offered')->count(),'Active assignments'=>(clone $jobs)->whereNull('trip_id')->where('status','accepted')->count(),'Completed assignments'=>(clone $jobs)->whereNull('trip_id')->where('status','completed')->count()];
 $today=(clone $jobs)->where('status','completed')->whereDate('updated_at',today())->sum('earnings');$active=(clone $jobs)->join('parcels as p','p.id','=','assignments.parcel_id')->where('assignments.status','accepted')->select('assignments.kind','p.tracking_code','p.status')->orderByDesc('assignments.id')->limit(8)->get();return view('courier_overview',['links'=>app(CommonController::class)->links(),'driver'=>$driver,'counts'=>$counts,'todayEarnings'=>$today,'activeJobs'=>$active,'center'=>DB::table('sorting_centers')->where('user_id',$u->company_id)->first(),'cod'=>DB::table('cod_collections')->where('rider_id',$u->id)->whereNull('remittance_id')->sum('amount')]);
 }
}
