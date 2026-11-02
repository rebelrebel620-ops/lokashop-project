<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\{ReportPdf,Support};
class AdminWorkspaceController extends Controller
{
 private function guard():void{abort_unless(auth()->user()->role==='admin',403);}
 private function search($q,string $search,array $columns):void{if(trim($search)==='')return;$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($search)).'%';$q->where(function($q)use($term,$columns){foreach($columns as $column)$q->orWhereRaw("$column LIKE ? ESCAPE '!'",[$term]);});}
 private function data(array $values):array{return ['links'=>app(CommonController::class)->links()]+$values;}
 public function reports(Request $r){
  $this->guard();$v=$r->validate(['q'=>'nullable|string|max:150','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d'.($r->filled('from')?'|after_or_equal:from':''),'seller'=>'nullable|integer|min:1','status'=>'nullable|string|max:40','format'=>'nullable|in:pdf']);
  $q=DB::table('orders as o')->leftJoin('parcels as p','p.order_id','=','o.id')->leftJoin('users as s','s.id','=','o.seller_id')->leftJoin('users as b','b.id','=','o.buyer_id')->leftJoin('seller_businesses as store','store.user_id','=','o.seller_id');
  if(!empty($v['from']))$q->whereDate('o.created_at','>=',$v['from']);if(!empty($v['to']))$q->whereDate('o.created_at','<=',$v['to']);if(!empty($v['seller']))$q->where('o.seller_id',$v['seller']);if(!empty($v['status']))$q->where('o.status',$v['status']);
  $term=trim($v['q']??'');if($term!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';$q->where(function($q)use($like){foreach(['CAST(o.id AS CHAR)','p.tracking_code','s.name','b.name','store.name'] as $column)$q->orWhereRaw("$column LIKE ? ESCAPE '!'",[$like]);$q->orWhereExists(fn($sub)=>$sub->selectRaw('1')->from('order_items as i')->whereColumn('i.order_id','o.id')->whereRaw("i.name LIKE ? ESCAPE '!'",[$like]));});}
  $stats=(clone $q)->selectRaw("COUNT(*) as orders, COALESCE(SUM(o.total),0) as order_value, SUM(CASE WHEN o.status IN ('DELIVERED','COMPLETED') AND o.refunded_at IS NULL THEN 1 ELSE 0 END) as delivered, COALESCE(SUM(CASE WHEN o.status='COMPLETED' AND o.refunded_at IS NULL THEN o.commission ELSE 0 END),0) as recorded_commission, SUM(CASE WHEN o.merchandise_total=0 AND o.total>o.shipping_fee THEN 1 ELSE 0 END) as legacy_records")->first();
  $query=(clone $q)->select('o.*','p.tracking_code','s.name as seller_name','b.name as buyer_name','store.name as store_name')->orderByDesc('o.id');
  if(($v['format']??'')==='pdf'){$rows=(clone $query)->get()->map(fn($o)=>(object)['id'=>$o->id,'seller'=>$o->store_name?:$o->seller_name,'tracking_code'=>$o->tracking_code,'status'=>$o->status,'merchandise_total'=>$o->merchandise_total,'commission'=>$o->commission,'shipping_fee'=>$o->shipping_fee,'total'=>$o->total,'created_at'=>$o->created_at]);return response(ReportPdf::make(['id','seller','tracking_code','status','merchandise_total','commission','shipping_fee','total','created_at'],$rows,$v),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="lokashop-admin-report.pdf"']);}
  $orders=$query->paginate(12)->withQueryString();$items=DB::table('order_items')->whereIn('order_id',$orders->pluck('id'))->get()->groupBy('order_id');
  return view('admin-reports',$this->data(['orders'=>$orders,'items'=>$items,'stats'=>$stats,'filters'=>$v,'sellers'=>DB::table('users')->where('role','seller')->orderBy('name')->get(['id','name']),'statuses'=>DB::table('orders')->distinct()->orderBy('status')->pluck('status')]));
 }
 public function complaints(Request $r){
  $this->guard();$v=$r->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:all,open,resolved']);$status=$v['status']??'open';
  $counts=DB::table('complaints')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status');
  $q=DB::table('complaints as c')->leftJoin('users as u','u.id','=','c.user_id')->leftJoin('orders as o','o.id','=','c.order_id')->leftJoin('seller_businesses as s','s.user_id','=','o.seller_id')->leftJoin('parcels as p','p.order_id','=','o.id');
  if($status!=='all')$q->where('c.status',$status);$this->search($q,$v['q']??'',['c.description','u.name','u.email','s.name','p.tracking_code','CAST(c.id AS CHAR)','CAST(c.order_id AS CHAR)']);
  $complaints=$q->select('c.*','u.name as buyer_name','u.email as buyer_email','s.name as store_name','p.tracking_code','o.status as order_status')->orderByDesc('c.id')->paginate(12)->withQueryString();
  return view('admin-complaints',$this->data(compact('complaints','counts','status')+['filters'=>$v]));
 }
 public function resolveComplaint(Request $r,int $id){
  $this->guard();$v=$r->validate(['resolution'=>'required|string|min:5|max:3000']);
  DB::transaction(function()use($id,$v){$c=DB::table('complaints')->where('id',$id)->lockForUpdate()->first();abort_unless($c,404);if($c->status==='resolved')return;abort_unless($c->status==='open',422);DB::table('complaints')->where('id',$id)->update($v+['status'=>'resolved','updated_at'=>now()]);Support::audit('complaint-resolved','complaint',$id,$c,$v);Support::notify([$c->user_id],'Your complaint #'.$id.' has been resolved: '.$v['resolution']);});
  return back()->with('success','Complaint resolved. The resolution is saved in its history.');
 }
 public function appeals(Request $r){
  $this->guard();$v=$r->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:all,pending,approved,rejected','role'=>'nullable|in:buyer,seller']);$status=$v['status']??'pending';
  $counts=DB::table('account_appeals')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status');
  $q=DB::table('account_appeals as a')->join('users as u','u.id','=','a.user_id');if($status!=='all')$q->where('a.status',$status);if(!empty($v['role']))$q->where('u.role',$v['role']);$this->search($q,$v['q']??'',['u.name','u.email','a.message','CAST(a.id AS CHAR)']);
  $appeals=$q->select('a.*','u.name','u.email','u.role','u.account_status','u.account_status_reason')->orderByDesc('a.id')->paginate(12)->withQueryString();
  return view('admin_appeals',$this->data(compact('appeals','counts','status')+['filters'=>$v]));
 }
 public function approvals(Request $r){
  $this->guard();$v=$r->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:pending,approved,rejected']);$status=$v['status']??'pending';
  $counts=DB::table('users')->where('role','seller')->selectRaw('approval_status, COUNT(*) as total')->groupBy('approval_status')->pluck('total','approval_status');
  $q=DB::table('users as u')->leftJoin('seller_businesses as s','s.user_id','=','u.id')->where('u.role','seller')->where('u.approval_status',$status);$this->search($q,$v['q']??'',['u.name','u.email','s.name','s.category']);
  $users=$q->select('u.*','s.name as store_name','s.category as store_category')->orderByDesc('u.id')->paginate(12)->withQueryString();
  return view('admin_approvals',$this->data(compact('users','counts','status')+['filters'=>$v,'addresses'=>DB::table('addresses')->whereIn('user_id',$users->pluck('id'))->orderByDesc('is_default')->orderByDesc('id')->get()->unique('user_id')->keyBy('user_id'),'docs'=>DB::table('registration_documents')->whereIn('user_id',$users->pluck('id'))->get()->groupBy('user_id')]));
 }
}
