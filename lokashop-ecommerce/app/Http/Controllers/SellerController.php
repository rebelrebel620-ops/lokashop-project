<?php
namespace App\Http\Controllers;
use App\Services\Workflow;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SellerController extends Controller {
 private function list($title,$query){return view('list',['title'=>$title,'links'=>app(CommonController::class)->links(),'records'=>$query->orderByDesc('id')->paginate(20)]);}
 public function products(){
  $categories=\App\Services\ProductCategories::seller(auth()->id())->get();
  $shopCategory=DB::table('seller_businesses')->where('user_id',auth()->id())->value('category');
  $defaultCategory=$categories->first(fn($c)=>mb_strtolower($c->name)===mb_strtolower((string)$shopCategory));
  return view('seller_products',['links'=>app(CommonController::class)->links(),'products'=>DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->orderByDesc('id')->paginate(20),'categories'=>$categories,'shopCategory'=>$shopCategory,'defaultCategoryId'=>$defaultCategory?->id]);
 }

 public function addProduct(Request $r){$v=$r->validate(['name'=>'required|max:180','description'=>'nullable|string','category_id'=>'required|exists:categories,id','price'=>'required|numeric|min:0.01','discount_percent'=>'required|numeric|between:0,100','stock'=>'required|integer|min:0']);DB::table('products')->insert($v+['seller_id'=>auth()->id(),'approved'=>0,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Product submitted for review.');}
 public function updateProduct(Request $r,int $id){$v=$r->validate(['name'=>'required|max:180','price'=>'required|numeric|min:0.01','stock'=>'required|integer|min:0','discount_percent'=>'required|numeric|between:0,100']);abort_unless(DB::table('products')->where('id',$id)->where('seller_id',auth()->id())->exists(),403);DB::table('products')->where('id',$id)->update($v+['approved'=>0,'updated_at'=>now()]);return back()->with('success','Updated; awaiting review.');}
 public function addVariation(Request $r,int $id){$v=$r->validate(['name'=>'required|max:120','price_adjustment'=>'required|numeric','stock'=>'required|integer|min:0']);abort_unless(DB::table('products')->where('id',$id)->where('seller_id',auth()->id())->exists(),403);DB::table('product_variations')->insert($v+['product_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Variation added.');}
 public function orders(){\App\Services\PickupRequests::refresh(null,auth()->id());return view('seller_orders',['links'=>app(CommonController::class)->links(),'riders'=>DB::table('users')->where('role','rider')->where('courier_type','rider')->where('approval_status','approved')->where('active',1)->get()->filter(function($u){$a=\App\Services\Municipalities::address(auth()->id());$c=DB::table('sorting_centers')->where('user_id',$u->company_id)->first();return \App\Services\Municipalities::matches($a,$c); }),'orders'=>DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')->where('o.seller_id',auth()->id())->select('o.*','p.id as parcel_id')->orderByDesc('o.id')->paginate(20)]);}
 public function transition(Request $r,int $id){$v=$r->validate(['status'=>'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP']);$p=DB::table('parcels')->where('order_id',$id)->first();abort_unless($p,404);Workflow::move($p->id,$v['status'],auth()->id());return back()->with('success','Status updated.');}
 public function voucher(Request $r){$v=$r->validate(['code'=>'required|max:60|unique:vouchers,code','discount_percent'=>'required|numeric|between:0,100','expires_at'=>'nullable|date|after:now']);DB::table('vouchers')->insert($v+['seller_id'=>auth()->id(),'active'=>1,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Voucher created.');}
 public function pickup(Request $r,int $id){$v=$r->validate(['rider_id'=>'required|exists:users,id']);\App\Services\PickupRequests::request($id,auth()->id(),(int)$v['rider_id']);return back()->with('success','Pickup requested. The rider must collect within 12 hours; your sorting center must verify the request first.');}

 public function label(int $id){$o=DB::table('orders')->where('id',$id)->where('seller_id',auth()->id())->first();abort_unless($o,404);$p=DB::table('parcels')->where('order_id',$id)->first();abort_unless($p,404);return view('label',['order'=>$o,'parcel'=>$p,'address'=>$o->address_snapshot?json_decode($o->address_snapshot):DB::table('addresses')->find($o->address_id),'trackUrl'=>url('/track/'.$p->tracking_code)]);}
 public function report(){return view('list',['title'=>'Sales and commission','links'=>app(CommonController::class)->links(),'records'=>DB::table('orders')->where('seller_id',auth()->id())->whereIn('status',['DELIVERED','COMPLETED'])->paginate(30)]);}
}
