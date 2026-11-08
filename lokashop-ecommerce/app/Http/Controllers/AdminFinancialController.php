<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\ReportPdf;
class AdminFinancialController extends Controller {
 public function index(Request $r){
  abort_unless(auth()->user()->role==='admin',403);
  $v=$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($r->filled('from')?'|after_or_equal:from':''),'q'=>'nullable|string|max:150','type'=>'nullable|in:payment,refund,payout,commission,rounding','status'=>'nullable|string|max:50','method'=>'nullable|in:cod,wallet,manual,external','format'=>'nullable|in:pdf']);
  // UNION ALL keeps each financial source independent, without multiplying rows through joins.
  $payments=DB::table('payments as p')->join('orders as o','o.id','=','p.order_id')->leftJoin('users as u','u.id','=','o.buyer_id')->selectRaw("p.id as source_id, 'payment' as type, p.order_id, p.amount, p.method, p.status, COALESCE(u.name,'Buyer') as party, p.created_at as occurred_at, 'Customer order payment' as description");
  $refunds=DB::table('refunds as r')->join('orders as o','o.id','=','r.order_id')->leftJoin('users as u','u.id','=','o.buyer_id')->selectRaw("r.id as source_id, 'refund' as type, r.order_id, r.amount, r.method, r.status, COALESCE(u.name,'Buyer') as party, r.created_at as occurred_at, 'Order refund' as description");
  $payouts=DB::table('payouts as p')->leftJoin('users as u','u.id','=','p.seller_id')->selectRaw("p.id as source_id, 'payout' as type, NULL as order_id, p.amount, CASE WHEN p.account='LokaShop demo wallet' THEN 'wallet' ELSE 'external' END as method, p.status, COALESCE(u.name,'Seller') as party, p.created_at as occurred_at, 'Legacy seller payout / earnings import' as description");
  $sales=DB::table('wallet_entries as w')->leftJoin('users as u','u.id','=','w.user_id')->where('w.kind','sale')->selectRaw("w.id as source_id, 'payout' as type, w.order_id, w.amount_cents / 100.0 as amount, 'wallet' as method, 'paid' as status, COALESCE(u.name,'Seller') as party, w.created_at as occurred_at, 'Seller earnings credited to wallet' as description");
  $commissions=DB::table('wallet_entries as w')->leftJoin('users as u','u.id','=','w.user_id')->where('w.kind','commission')->selectRaw("w.id as source_id, 'commission' as type, w.order_id, w.amount_cents / 100.0 as amount, 'wallet' as method, 'paid' as status, COALESCE(u.name,'Admin') as party, w.created_at as occurred_at, 'Platform commission credited to wallet' as description");
  $rounding=DB::table('orders as o')->where('o.cod_rounding_adjustment','!=',0)->whereExists(fn($q)=>$q->selectRaw('1')->from('payments')->whereColumn('payments.order_id','o.id')->where('payments.method','cod'))->selectRaw("o.id as source_id, 'rounding' as type, o.id as order_id, o.cod_rounding_adjustment as amount, 'cod' as method, CASE WHEN EXISTS (SELECT 1 FROM payments p WHERE p.order_id=o.id AND p.method='cod' AND p.status IN ('paid','refunded')) THEN 'paid' ELSE 'pending' END as status, 'COD rounding adjustment' as party, o.created_at as occurred_at, 'Nearest-peso COD adjustment; participant earnings unchanged' as description");
  $union=$payments->unionAll($refunds)->unionAll($payouts)->unionAll($sales)->unionAll($commissions)->unionAll($rounding);
  $q=DB::query()->fromSub($union,'finance');
  if(!empty($v['from']))$q->whereDate('occurred_at','>=',$v['from']);if(!empty($v['to']))$q->whereDate('occurred_at','<=',$v['to']);
  foreach(['type','status','method'] as $field)if(!empty($v[$field]))$q->where($field,$v[$field]);
  $search=trim($v['q']??'');if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$q->where(function($q)use($term){foreach(['party','description','order_id','source_id'] as $i=>$field)$q->{$i?'orWhereRaw':'whereRaw'}("CAST($field AS CHAR) LIKE ? ESCAPE '!'",[$term]);});}
  $stats=(clone $q)->selectRaw("COALESCE(SUM(CASE WHEN type='payment' AND status IN ('paid','refunded') THEN amount ELSE 0 END),0) AS collected, COALESCE(SUM(CASE WHEN type='refund' AND status='paid' THEN amount ELSE 0 END),0) AS refunded, COALESCE(SUM(CASE WHEN type='payout' AND status='paid' THEN amount ELSE 0 END),0) AS settled, COALESCE(SUM(CASE WHEN type='commission' AND status='paid' THEN amount ELSE 0 END),0) AS commission")->first();
  $q->orderByDesc('occurred_at')->orderBy('type')->orderByDesc('source_id')->orderBy('description');
  if(($v['format']??'')==='pdf'){$rows=(clone $q)->get()->map(fn($x)=>(object)['date'=>$x->occurred_at,'type'=>$x->type,'order'=>$x->order_id??'-','party'=>$x->party,'method'=>$x->method,'status'=>$x->status,'amount'=>number_format((float)$x->amount,2,'.',',')]);return response(ReportPdf::make(['date','type','order','party','method','status','amount'],$rows,$v+['_title'=>'Financial report']),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="lokashop-financial-report.pdf"']);}
  return view('admin-financial',['links'=>app(CommonController::class)->links(),'rows'=>$q->paginate(12)->withQueryString(),'stats'=>$stats,'filters'=>$v]);
 }
}
