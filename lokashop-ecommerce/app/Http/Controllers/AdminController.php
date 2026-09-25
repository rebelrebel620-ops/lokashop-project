<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminController extends Controller {
 private function listing($title,$query){return view('list',['title'=>$title,'links'=>app(CommonController::class)->links(),'records'=>$query->orderByDesc('id')->paginate(30)]);}
 public function approvals(){$users=DB::table('users')->whereIn('role',['buyer','seller','sorting_center'])->where('approval_status','pending')->paginate(20);return view('admin_approvals',['links'=>app(CommonController::class)->links(),'users'=>$users,'docs'=>DB::table('registration_documents')->whereIn('user_id',$users->pluck('id'))->get()->groupBy('user_id')]);}
 public function approve(Request $r,int $id){$v=$r->validate(['decision'=>'required|in:approved,rejected']);abort_unless(DB::table('users')->where('id',$id)->whereIn('role',['buyer','seller','sorting_center'])->where('approval_status','pending')->update(['approval_status'=>$v['decision'],'updated_at'=>now()]),422);DB::table('notifications')->insert(['user_id'=>$id,'body'=>'Your registration is '.$v['decision'],'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Decision saved.');}
 public function users(){return $this->listing('Accounts',DB::table('users')->select('id','name','email','role','approval_status','active','created_at'));}
 public function toggle(int $id){abort_if($id===auth()->id(),422);$u=DB::table('users')->where('id',$id)->first();abort_unless($u,404);DB::table('users')->where('id',$id)->update(['active'=>!$u->active,'updated_at'=>now()]);return back()->with('success','Account changed.');}
 public function products(){return view('admin_products',['links'=>app(CommonController::class)->links(),'products'=>DB::table('products')->where('approved',0)->paginate(20)]);}
 public function productDecision(Request $r,int $id){$v=$r->validate(['decision'=>'required|in:approve,reject']);DB::table('products')->where('id',$id)->update(['approved'=>$v['decision']==='approve','active'=>$v['decision']==='approve','updated_at'=>now()]);return back()->with('success','Product reviewed.');}
 public function report(){return $this->listing('Orders and 10% platform commission',DB::table('orders')->whereIn('status',['DELIVERED','COMPLETED']));}
 public function announcement(Request $r){$v=$r->validate(['title'=>'required|max:150','body'=>'required|max:5000']);DB::table('announcements')->insert($v+['created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Published.');}
 public function announcements(){return view('announcements',['links'=>app(CommonController::class)->links(),'records'=>DB::table('announcements')->orderByDesc('id')->get()]);}
 public function complaints(){return view('complaints',['links'=>app(CommonController::class)->links(),'complaints'=>DB::table('complaints')->orderByDesc('id')->get()]);}
 public function document(int $id){$d=DB::table('registration_documents')->find($id);abort_unless($d,404);return response()->file(storage_path('app/private/'.$d->path));}
 public function resolve(Request $r,int $id){$v=$r->validate(['resolution'=>'required|string|max:3000']);DB::table('complaints')->where('id',$id)->update(['resolution'=>$v['resolution'],'status'=>'resolved','updated_at'=>now()]);return back()->with('success','Resolved.');}
}
