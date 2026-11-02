<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminController extends Controller {
 private function listing($title,$query){return view('list',['title'=>$title,'links'=>app(CommonController::class)->links(),'records'=>$query->orderByDesc('id')->paginate(30)]);}
 public function approvals(Request $r){return app(AdminWorkspaceController::class)->approvals($r);}
 public function approve(Request $r,int $id){$v=$r->validate(['decision'=>'required|in:approved,rejected']);abort_unless(DB::table('users')->where('id',$id)->where('role','seller')->where('approval_status','pending')->update(['approval_status'=>$v['decision'],'updated_at'=>now()]),422);DB::table('notifications')->insert(['user_id'=>$id,'body'=>'Your registration is '.$v['decision'],'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Decision saved.');}
 public function users(Request $r){
  $filters=$r->validate(['search'=>'nullable|string|max:150','role'=>'nullable|in:buyer,seller','state'=>'nullable|in:active,suspended,banned,archived']);
  $search=trim($filters['search'] ?? '');
  $role=$filters['role'] ?? '';
  $state=$filters['state']??'';
  $query=DB::table('users')->whereIn('role',['buyer','seller']);
  if($state!=='')$query->where('account_status',$state);else $query->where('account_status','!=','archived');
  if($search!=='')$query->whereRaw("name LIKE ? ESCAPE '!'",['%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%']);
  if($role!=='')$query->where('role',$role);
  return view('admin_users',['links'=>app(CommonController::class)->links(),'users'=>$query->select('id','name','email','role','approval_status','active','verification_required','email_verified_at','created_at','account_status')->orderByDesc('id')->paginate(12)->withQueryString(),'search'=>$search,'role'=>$role,'state'=>$state]);
 }

 public function toggle(int $id){\App\Services\Support::fail('status','Use the account status menu and provide a reason.');abort_if($id===auth()->id(),422);$u=DB::table('users')->where('id',$id)->whereIn('role',['buyer','seller'])->first();abort_unless($u,404);DB::table('users')->where('id',$id)->whereIn('role',['buyer','seller'])->update(['active'=>!$u->active,'updated_at'=>now()]);return back()->with('success','Account changed.');}
 public function products(Request $r){
  $filters=$r->validate(['tab'=>'nullable|in:pending,approved','q'=>'nullable|string|max:150','category'=>'nullable|string|max:150','stock'=>'nullable|in:available,empty','visibility'=>'nullable|in:active,inactive']);
  $tab=$filters['tab']??'pending';
  $query=DB::table('products as p')->leftJoin('users as u','u.id','=','p.seller_id')->leftJoin('categories as c','c.id','=','p.category_id')->whereNull('p.deleted_at')->where('p.draft',0)->where('p.approved',$tab==='approved'?1:0);
  if($tab==='pending')$query->where('p.active',1);
  $search=trim($filters['q']??'');
  if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$query->where(function($q)use($term){foreach(['p.name','u.name','u.email'] as $column)$q->orWhereRaw("$column LIKE ? ESCAPE '!'",[$term]);});}
  if(!empty($filters['category']))$query->where('c.main_category',$filters['category']);
  if(($filters['stock']??'')==='available')$query->where('p.stock','>',0);
  if(($filters['stock']??'')==='empty')$query->where('p.stock','<=',0);
  if($tab==='approved'&&!empty($filters['visibility']))$query->where('p.active',$filters['visibility']==='active'?1:0);
  return view('admin_products',['links'=>app(CommonController::class)->links(),'products'=>$query->select('p.*','u.name as seller_name','u.email as seller_email','c.name as subcategory','c.main_category')->orderByDesc('p.id')->paginate(12)->withQueryString(),'tab'=>$tab,'filters'=>$filters,'categories'=>DB::table('categories')->whereNotNull('main_category')->distinct()->orderBy('main_category')->pluck('main_category')]);
 }
 public function productDecision(Request $r,int $id){$v=$r->validate(['decision'=>'required|in:approve,reject']);$changed=DB::table('products')->where('id',$id)->whereNull('deleted_at')->where('draft',0)->where('approved',0)->where('active',1)->update(['approved'=>$v['decision']==='approve','active'=>$v['decision']==='approve','updated_at'=>now()]);abort_unless($changed,422,'This product is no longer awaiting review.');return back()->with('success',$v['decision']==='approve'?'Product approved.':'Product rejected.');}
 public function report(){return $this->listing('Orders and 10% platform commission',DB::table('orders')->whereIn('status',['DELIVERED','COMPLETED']));}
 public function announcement(Request $r){$v=$r->validate(['title'=>'required|max:150','body'=>'required|max:5000']);DB::table('announcements')->insert($v+['created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Published.');}
 public function announcements(){return view('announcements',['links'=>app(CommonController::class)->links(),'records'=>DB::table('announcements')->orderByDesc('id')->get()]);}
 public function complaints(Request $r){return app(AdminWorkspaceController::class)->complaints($r);}
 public function document(int $id){$d=DB::table('registration_documents as d')->join('users as u','u.id','=','d.user_id')->where('d.id',$id)->where('u.role','seller')->select('d.*')->first();abort_unless($d,404);return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($d->path));}
 public function resolve(Request $r,int $id){return app(AdminWorkspaceController::class)->resolveComplaint($r,$id);}
}
