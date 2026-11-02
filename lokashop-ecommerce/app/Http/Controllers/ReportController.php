<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReportController extends Controller {
 public function index(Request $r){if(auth()->user()->role==='admin')return app(AdminWorkspaceController::class)->reports($r);if(auth()->user()->role==='seller')return app(SellerSalesController::class)->index($r);$v=$r->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from','format'=>'nullable|in:pdf','q'=>'nullable|string|max:150']);$u=auth()->user();
  if($u->role==='seller')$q=DB::table('orders')->where('seller_id',$u->id)->select('id','status','merchandise_total','store_discount','commission','shipping_fee','total','created_at','completed_at');
  elseif($u->role==='admin')$q=DB::table('orders')->select('id','seller_id','status','merchandise_total','store_discount','commission','shipping_fee','total','created_at');
  elseif($u->role==='sorting_center'){$center=DB::table('sorting_centers')->where('user_id',$u->id)->value('id');abort_unless($center,403);$q=DB::table('parcels')->where('center_id',$center)->select('id','order_id','tracking_code','status','attempts','created_at');}
  else abort(403);
  if(!empty($v['from']))$q->whereDate('created_at','>=',$v['from']);if(!empty($v['to']))$q->whereDate('created_at','<=',$v['to']);$search=trim($v['q']??'');if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$q->where(function($query)use($term,$u){$query->whereRaw("status LIKE ? ESCAPE '!'",[$term])->orWhereRaw("CAST(id AS CHAR) LIKE ? ESCAPE '!'",[$term]);if($u->role==='sorting_center')$query->orWhereRaw("tracking_code LIKE ? ESCAPE '!'",[$term])->orWhereRaw("CAST(order_id AS CHAR) LIKE ? ESCAPE '!'",[$term]);});}$rows=$q->orderBy('id')->get();
  $headers=$u->role==='sorting_center'?['id','order_id','tracking_code','status','attempts','created_at']:($u->role==='seller'?['id','status','merchandise_total','store_discount','commission','shipping_fee','total','created_at','completed_at']:['id','seller_id','status','merchandise_total','store_discount','commission','shipping_fee','total','created_at']);
  if(($v['format']??'')==='pdf')return response(\App\Services\ReportPdf::make($headers,$rows,$v),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="lokashop-report.pdf"']);
  return view('tools',['title'=>'Filtered report','records'=>$rows,'forms'=>[],'links'=>app(CommonController::class)->links(),'report'=>true]);
 }
}
