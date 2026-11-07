<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\{Wallet,WalletEarnings,ReportPdf};
class CenterFinancialController extends Controller {
 public function index(Request $r){
  abort_unless(auth()->user()->role==='sorting_center',403);$owner=auth()->id();
  $v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($r->filled('from')?'|after_or_equal:from':''),'q'=>'nullable|string|max:150','type'=>'nullable|in:shipping_income,courier_earnings,courier_allocation,center_settlement,cod_collection,cod_remittance','status'=>'nullable|string|max:40','format'=>'nullable|in:pdf']);
  $income=DB::table('wallet_entries as w')->where('w.user_id',$owner)->where('w.kind','shipping_income')->selectRaw("w.id as source_id, 'shipping_income' as type, w.created_at as recorded_at, 'Center shipping income' as party, w.reference as reference, 'posted' as status, w.amount_cents/100.0 as amount, NULL as expected");
  $earnings=DB::table('wallet_courier_payments as w')->leftJoin('users as u','u.id','=','w.courier_user_id')->where('w.center_user_id',$owner)->selectRaw("w.assignment_id as source_id, CASE WHEN w.funding_source='shipping_pool' THEN 'courier_allocation' ELSE 'courier_earnings' END as type, w.created_at as recorded_at, COALESCE(u.name,'Courier') as party, CAST(w.assignment_id AS CHAR) as reference, w.status, w.amount_cents/100.0 as amount, NULL as expected");
  $settlements=DB::table('wallet_entries as w')->leftJoin('users as u','u.id','=','w.counterparty_id')->where('w.user_id',$owner)->where('w.kind','courier_payment')->selectRaw("w.id as source_id, 'center_settlement' as type, w.created_at as recorded_at, COALESCE(u.name,'Courier') as party, w.reference, 'paid' as status, -w.amount_cents/100.0 as amount, NULL as expected");
  $collections=DB::table('cod_collections as c')->leftJoin('users as u','u.id','=','c.rider_id')->leftJoin('remittances as r','r.id','=','c.remittance_id')->leftJoin('parcels as p','p.id','=','c.parcel_id')->where('c.company_id',$owner)->selectRaw("c.id as source_id, 'cod_collection' as type, c.created_at as recorded_at, COALESCE(u.name,'Rider') as party, COALESCE(p.tracking_code,'COD collection') as reference, COALESCE(r.status,'unremitted') as status, c.amount, NULL as expected");
  $remittances=DB::table('remittances as r')->leftJoin('users as u','u.id','=','r.rider_id')->where('r.company_id',$owner)->selectRaw("r.id as source_id, 'cod_remittance' as type, r.created_at as recorded_at, COALESCE(u.name,'Rider') as party, r.reference, r.status, r.amount, r.expected");
  $q=DB::query()->fromSub($income->unionAll($earnings)->unionAll($settlements)->unionAll($collections)->unionAll($remittances),'finance');
  if(!empty($v['from']))$q->whereDate('recorded_at','>=',$v['from']);if(!empty($v['to']))$q->whereDate('recorded_at','<=',$v['to']);foreach(['type','status'] as $field)if(!empty($v[$field]))$q->where($field,$v[$field]);
  $search=trim($v['q']??'');if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$q->where(fn($q)=>$q->whereRaw("party LIKE ? ESCAPE '!'",[$term])->orWhereRaw("reference LIKE ? ESCAPE '!'",[$term]));}
  $stats=(clone $q)->selectRaw("COALESCE(SUM(CASE WHEN type='shipping_income' THEN amount ELSE 0 END),0) as income, COALESCE(SUM(CASE WHEN type='courier_earnings' THEN amount ELSE 0 END),0) as courier_cost, COALESCE(SUM(CASE WHEN type='center_settlement' THEN amount ELSE 0 END),0) as settled, COALESCE(SUM(CASE WHEN type='cod_collection' THEN amount ELSE 0 END),0) as cod_collected, COALESCE(SUM(CASE WHEN type='cod_remittance' AND status='confirmed' THEN amount ELSE 0 END),0) as cod_received")->first();
  $q->orderByDesc('recorded_at')->orderBy('type')->orderByDesc('source_id');
  if(($v['format']??'')==='pdf'){$rows=(clone $q)->get()->map(fn($x)=>(object)['date'=>$x->recorded_at,'type'=>$x->type,'person'=>$x->party,'reference'=>$x->reference,'status'=>$x->status,'amount'=>number_format($x->amount,2,'.',','),'expected'=>$x->expected===null?'':number_format($x->expected,2,'.',',')]);return response(ReportPdf::make(['date','type','person','reference','status','amount','expected'],$rows,$v+['_title'=>'Sorting center financial report']),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="lokashop-center-financial-report.pdf"']);}
  return view('center-financial',['links'=>app(CommonController::class)->links(),'rows'=>$q->paginate(12)->withQueryString(),'stats'=>$stats,'filters'=>$v,'balance'=>Wallet::balance($owner)/100,'owed'=>WalletEarnings::unsettled()->where('center_user_id',$owner)->sum('amount_cents')/100]);
 }
}
