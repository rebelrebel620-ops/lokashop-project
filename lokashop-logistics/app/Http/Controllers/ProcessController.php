<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\{ProcessRouting,PickupRequests,DeliveryOffers};
class ProcessController extends Controller {
 private function center(){abort_unless(auth()->user()->role==='sorting_center',403);$c=DB::table('sorting_centers')->where('user_id',auth()->id())->first();abort_unless($c,403);return $c;}
 public function overview(){
  $c=$this->center();PickupRequests::refresh();DeliveryOffers::refresh();
  $parcels=DB::table('parcels')->where('center_id',$c->id)->get();
  $pickup=$parcels->whereIn('status',['PREPARING','READY_FOR_PICKUP','PICKED_UP'])->count();
  $atCenter=$parcels->whereIn('status',['AT_SORTING_CENTER','SORTED','DELIVERY_FAILED','ASSIGNED_TO_RIDER','OUT_FOR_DELIVERY']);
  $delivery=$atCenter->filter(fn($p)=>ProcessRouting::local($p))->count();
  $transfer=$parcels->whereIn('status',['TRANSFER_OFFERED','IN_TRANSIT_CENTER'])->count()+$parcels->whereIn('status',['AT_SORTING_CENTER','SORTED'])->reject(fn($p)=>ProcessRouting::local($p))->count()+DB::table('parcels')->where('destination_center_id',$c->id)->whereIn('status',['TRANSFER_OFFERED','IN_TRANSIT_CENTER'])->count();
  return view('process_overview',['links'=>app(CommonController::class)->links(),'center'=>$c,'pickup'=>$pickup,'transfer'=>$transfer,'delivery'=>$delivery]);
 }
 public function setup(){ $this->center();return view('process_setup',['links'=>app(CommonController::class)->links()]); }
 public function history(){
  $c=$this->center();$records=DB::table('parcels')->where('center_id',$c->id)->whereIn('status',['DELIVERED','COMPLETED','CANCELLED','RETURNED','RETURN_RECEIVED','REFUNDED','LOST','DAMAGED'])->orderByDesc('id')->paginate(20);
  return view('list',['title'=>'History — delivered and closed parcels','links'=>app(CommonController::class)->links(),'records'=>$records]);
 }
}
