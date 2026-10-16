<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Support;
class DraftController extends Controller {
 private function guard():void {abort_unless(auth()->check()&&auth()->user()->role==='seller'&&auth()->user()->active&&auth()->user()->account_status==='active'&&in_array(auth()->user()->approval_status,['pending','approved'])&&(!auth()->user()->verification_required||auth()->user()->email_verified_at),403);}
 public function form(){$this->guard();return view('guest-tools',['title'=>'Seller application under review — drafts only','forms'=>[['label'=>'Save draft (not published)','action'=>'/application/drafts','fields'=>['id:number?','name','description:textarea?','category_id:number?','price:number?','stock:number?']]]]);}
 public function save(Request $r){$this->guard();$v=$r->validate(['id'=>'nullable|integer','name'=>'required|string|min:5|max:120','description'=>'nullable|string|max:3000','category_id'=>'nullable|integer|exists:categories,id','price'=>'nullable|numeric|decimal:0,2|between:1,1000000','stock'=>'nullable|integer|between:0,99999']);$id=$v['id']??null;unset($v['id']);$v['category_id']=$v['category_id']??\App\Services\ProductCategories::seller(auth()->id())->value('id');if(!$v['category_id'])Support::fail('category_id','Ask the administrator to create a platform category first.');\App\Services\ProductCategories::validate($v['category_id']);$v['price']=$v['price']??1;$v['stock']=$v['stock']??0;$v+=['draft'=>1,'approved'=>0,'active'=>0,'updated_at'=>now()];if($id){abort_unless(DB::table('products')->where('id',$id)->where('seller_id',auth()->id())->exists(),403);DB::table('products')->where('id',$id)->update($v);}else $id=Support::once($r,'pending-draft',fn()=>DB::table('products')->insertGetId($v+['seller_id'=>auth()->id(),'created_at'=>now()]));return back()->with('success','Draft #'.$id.' saved. It cannot be published until your seller account is approved.');}
}
