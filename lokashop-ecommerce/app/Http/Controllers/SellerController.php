<?php
namespace App\Http\Controllers;
use App\Services\Workflow;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SellerController extends Controller {
 private function list($title,$query){return view('list',['title'=>$title,'links'=>app(CommonController::class)->links(),'records'=>$query->orderByDesc('id')->paginate(20)]);}
 public function stock(){return response()->json(DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->get(['id','stock']));}
 public function products(){
  $categories=\App\Services\ProductCategories::seller(auth()->id())->get();
  $shopCategory=DB::table('seller_businesses')->where('user_id',auth()->id())->value('category');
  $defaultCategory=$categories->first(fn($c)=>mb_strtolower($c->name)===mb_strtolower((string)$shopCategory));
  return view('seller_products',['links'=>app(CommonController::class)->links(),'variantsByProduct'=>DB::table('product_variations')->whereIn('product_id',DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->select('id'))->whereNull('deleted_at')->orderBy('id')->get()->groupBy('product_id'),'products'=>DB::table('products')->where('seller_id',auth()->id())->whereNull('deleted_at')->when(request()->filled('q'),fn($q)=>$q->where('name','like','%'.mb_substr((string)request('q'),0,120).'%'))->orderByDesc('id')->paginate(12)->withQueryString(),'categories'=>$categories,'shopCategory'=>$shopCategory,'defaultCategoryId'=>$defaultCategory?->id]);
 }

 public function addProduct(Request $r){$v=$r->validate(['name'=>'required|max:180','description'=>'nullable|string','category_id'=>'required|exists:categories,id','price'=>'required|numeric|min:0.01','discount_percent'=>'required|numeric|between:0,100','stock'=>'required|integer|min:0']);DB::table('products')->insert($v+['seller_id'=>auth()->id(),'approved'=>0,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Product submitted for review.');}
 public function updateProduct(Request $r,int $id){$v=$r->validate(['name'=>'required|max:180','price'=>'required|numeric|min:0.01','stock'=>'required|integer|min:0','discount_percent'=>'required|numeric|between:0,100']);abort_unless(DB::table('products')->where('id',$id)->where('seller_id',auth()->id())->exists(),403);DB::table('products')->where('id',$id)->update($v+['updated_at'=>now()]);return back()->with('success','Product updated. Existing approval is retained.');}
 public function addVariation(Request $r,int $id){$r->merge(['product_id'=>$id]);return app(MarketController::class)->variant($r);}
 public function orders(){
  \App\Services\PickupRequests::refresh(null,auth()->id());
  $closed=['DELIVERED','COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED'];
  $tab=(string)request('tab','pending');if(!in_array($tab,['pending','receive','completed'],true))$tab='pending';
  $base=DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')->leftJoin('users as b','b.id','=','o.buyer_id')->where('o.seller_id',auth()->id());
  $counts=['pending'=>(clone $base)->where('o.status','PLACED')->count(),'receive'=>(clone $base)->where('o.status','!=','PLACED')->whereNotIn('o.status',$closed)->count(),'completed'=>(clone $base)->whereIn('o.status',$closed)->count()];
  $query=clone $base;if($tab==='pending')$query->where('o.status','PLACED');elseif($tab==='completed')$query->whereIn('o.status',$closed);else $query->where('o.status','!=','PLACED')->whereNotIn('o.status',$closed);
  $search=mb_substr(trim((string)request('q','')),0,120);if($search!=='')$query->where(function($q)use($search){$q->where('p.tracking_code','like','%'.$search.'%')->orWhere('b.name','like','%'.$search.'%')->orWhereIn('o.id',DB::table('order_items')->where('name','like','%'.$search.'%')->select('order_id'));if(ctype_digit(ltrim($search,'#')))$q->orWhere('o.id',(int)ltrim($search,'#'));});
  $orders=$query->select('o.*','p.id as parcel_id','p.tracking_code','b.name as buyer_name')->orderByDesc('o.id')->paginate(12)->withQueryString();
  $items=DB::table('order_items')->whereIn('order_id',$orders->pluck('id'))->get()->groupBy('order_id');
  $pickups=DB::table('assignments')->whereIn('parcel_id',$orders->pluck('parcel_id'))->where('kind','pickup')->orderByDesc('id')->get()->unique('parcel_id')->keyBy('parcel_id');
  return view('seller_orders',['links'=>app(CommonController::class)->links(),'orders'=>$orders,'orderItems'=>$items,'pickups'=>$pickups,'counts'=>$counts,'tab'=>$tab,'search'=>$search]);
 }
 public function transition(Request $r,int $id){$v=$r->validate(['status'=>'required|in:CONFIRMED,PREPARING,READY_FOR_PICKUP']);$p=DB::table('parcels')->where('order_id',$id)->first();abort_unless($p,404);Workflow::move($p->id,$v['status'],auth()->id());return back()->with('success','Status updated.');}
 public function voucher(Request $r){$v=$r->validate(['code'=>'required|max:60|unique:vouchers,code','discount_percent'=>'required|numeric|between:0,100','expires_at'=>'nullable|date|after:now']);DB::table('vouchers')->insert($v+['seller_id'=>auth()->id(),'active'=>1,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Voucher created.');}
 public function pickup(Request $r,int $id){\App\Services\PickupRequests::request($id,auth()->id());return back()->with('success','Pickup requested. Your sorting center assigns an area rider, who has 3 hours to accept.');}

 public function label(int $id){$o=DB::table('orders')->where('id',$id)->where('seller_id',auth()->id())->first();abort_unless($o,404);$p=DB::table('parcels')->where('order_id',$id)->first();abort_unless($p,404);return view('label',['order'=>$o,'parcel'=>$p,'address'=>$o->address_snapshot?json_decode($o->address_snapshot):DB::table('addresses')->find($o->address_id),'trackUrl'=>url('/track/'.$p->tracking_code)]);}
 public function report(){return view('list',['title'=>'Sales and commission','links'=>app(CommonController::class)->links(),'records'=>DB::table('orders')->where('seller_id',auth()->id())->whereIn('status',['DELIVERED','COMPLETED'])->paginate(30)]);}
}
