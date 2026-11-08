<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\ReportPdf;
class CourierFinancialController extends Controller {
 public function index(Request $r){
  abort_unless(auth()->user()->role==='rider',403);
  $v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($r->filled('from')?'|after_or_equal:from':''),'q'=>'nullable|string|max:150','kind'=>'nullable|in:pickup,delivery,transfer','payment'=>'nullable|in:paid,unpaid','format'=>'nullable|in:pdf']);
  $earned='COALESCE(w.amount_cents / 100.0,s.earnings,0)';
  $q=DB::table('assignments as s')->join('parcels as p','p.id','=','s.parcel_id')->join('orders as o','o.id','=','p.order_id')->leftJoin('shipping_distributions as d','d.order_id','=','o.id')->leftJoin('wallet_courier_payments as w','w.assignment_id','=','s.id')->leftJoin('users as c','c.id','=','w.center_user_id')->where('s.rider_id',auth()->id())->where('s.status','completed');
  if(!empty($v['from']))$q->whereDate('s.updated_at','>=',$v['from']);if(!empty($v['to']))$q->whereDate('s.updated_at','<=',$v['to']);if(!empty($v['kind']))$q->where('s.kind',$v['kind']);
  if(($v['payment']??'')==='paid')$q->where('w.status','paid');
  if(($v['payment']??'')==='unpaid')$q->whereRaw($earned.' > 0')->where(fn($q)=>$q->whereNull('w.status')->orWhere('w.status','!=','paid'));
  $search=trim($v['q']??'');if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$q->where(fn($q)=>$q->whereRaw("p.tracking_code LIKE ? ESCAPE '!'",[$term])->orWhereRaw("CAST(s.id AS CHAR) LIKE ? ESCAPE '!'",[$term])->orWhereRaw("CAST(p.order_id AS CHAR) LIKE ? ESCAPE '!'",[$term])->orWhereRaw("c.name LIKE ? ESCAPE '!'",[$term]));}
  $stats=(clone $q)->selectRaw("COUNT(*) as jobs, COALESCE(SUM($earned),0) as earned, COALESCE(SUM(CASE WHEN w.status='paid' THEN $earned ELSE 0 END),0) as paid, COALESCE(SUM(CASE WHEN w.status='paid' THEN 0 ELSE $earned END),0) as unpaid, SUM(CASE WHEN o.shipping_split_version=2 AND d.finalized_at IS NULL AND s.kind='transfer' THEN 1 ELSE 0 END) as awaiting_allocation")->first();
  $rows=$q->select('s.id','s.kind','s.trip_id','s.updated_at as completed_at','p.order_id','p.tracking_code','c.name as center_name','w.status as payment_status','w.paid_at','o.shipping_split_version','d.finalized_at')->selectRaw($earned.' as earned_amount')->orderByDesc('s.updated_at')->orderByDesc('s.id');
  if(($v['format']??'')==='pdf'){$export=(clone $rows)->get()->map(fn($x)=>(object)['job'=>$x->id,'tracking'=>$x->tracking_code,'kind'=>$x->kind,'completed'=>$x->completed_at,'earned'=>number_format($x->earned_amount,2,'.',','),'wallet_paid'=>number_format($x->payment_status==='paid'?$x->earned_amount:0,2,'.',','),'payment'=>$x->kind==='transfer'&&$x->shipping_split_version==2&&!$x->finalized_at?'Awaiting delivery allocation':($x->earned_amount<=0?'No fee':($x->payment_status??'Not queued'))]);return response(ReportPdf::make(['job','tracking','kind','completed','earned','wallet_paid','payment'],$export,$v+['_title'=>'Courier financial report']),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="lokashop-courier-financial-report.pdf"']);}
  return view('courier-financial',['links'=>app(CommonController::class)->links(),'rows'=>$rows->paginate(12)->withQueryString(),'stats'=>$stats,'filters'=>$v,'isDriver'=>auth()->user()->courier_type==='driver']);
 }
}
