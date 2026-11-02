<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ReportPdf;
class SellerSalesController extends Controller
{
 public function index(Request $r){
  abort_unless(auth()->user()->role==='seller',403);
  $v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($r->filled('from')?'|after_or_equal:from':''),'q'=>'nullable|string|max:150','status'=>'nullable|string|max:40','format'=>'nullable|in:pdf']);
  $q=DB::table('orders as o')->leftJoin('parcels as p','p.order_id','=','o.id')->leftJoin('users as b','b.id','=','o.buyer_id')->where('o.seller_id',auth()->id());
  if(!empty($v['from']))$q->whereDate('o.created_at','>=',$v['from']);if(!empty($v['to']))$q->whereDate('o.created_at','<=',$v['to']);if(!empty($v['status']))$q->where('o.status',$v['status']);
  $search=trim($v['q']??'');if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$q->where(function($q)use($term){$q->whereRaw("CAST(o.id AS CHAR) LIKE ? ESCAPE '!'",[$term])->orWhereRaw("p.tracking_code LIKE ? ESCAPE '!'",[$term])->orWhereRaw("b.name LIKE ? ESCAPE '!'",[$term])->orWhereExists(fn($sub)=>$sub->selectRaw('1')->from('order_items as i')->whereColumn('i.order_id','o.id')->whereRaw("i.name LIKE ? ESCAPE '!'",[$term]));});}
  $stats=(clone $q)->selectRaw("COUNT(*) AS orders, COALESCE(SUM(o.total),0) AS order_value, SUM(CASE WHEN o.status IN ('DELIVERED','COMPLETED') AND o.refunded_at IS NULL THEN 1 ELSE 0 END) AS delivered, COALESCE(SUM(CASE WHEN o.status='COMPLETED' AND o.refunded_at IS NULL THEN o.merchandise_total-o.store_discount ELSE 0 END),0) AS completed_sales")->first();
  $rows=(clone $q)->select('o.*','p.tracking_code','b.name as buyer_name')->orderByDesc('o.id');
  if(($v['format']??'')==='pdf'){
   $export=(clone $rows)->get()->map(fn($o)=>(object)['id'=>$o->id,'tracking_code'=>$o->tracking_code??'','status'=>$o->status,'merchandise_total'=>$o->merchandise_total,'store_discount'=>$o->store_discount,'commission'=>$o->commission,'shipping_fee'=>$o->shipping_fee,'total'=>$o->total,'created_at'=>$o->created_at]);
   return response(ReportPdf::make(['id','tracking_code','status','merchandise_total','store_discount','commission','shipping_fee','total','created_at'],$export,$v),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="lokashop-sales.pdf"']);
  }
  $orders=$rows->paginate(12)->withQueryString();$items=DB::table('order_items')->whereIn('order_id',$orders->pluck('id'))->get()->groupBy('order_id');
  $statuses=DB::table('orders')->where('seller_id',auth()->id())->distinct()->orderBy('status')->pluck('status');
  return view('seller-sales',['links'=>app(CommonController::class)->links(),'orders'=>$orders,'items'=>$items,'stats'=>$stats,'statuses'=>$statuses,'filters'=>$v]);
 }
}
