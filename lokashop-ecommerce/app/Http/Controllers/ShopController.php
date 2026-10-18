<?php
namespace App\Http\Controllers;
use App\Services\Workflow;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ShopController extends Controller {
 private function links(){return app(CommonController::class)->links();}
 public function landing(){$b=fn()=>\App\Services\Commerce::catalog(DB::table('products'));return view('landing',['featured'=>$b()->join('categories','categories.id','=','products.category_id')->select('products.*','categories.name as category')->orderByDesc('products.id')->limit(8)->get(),'categories'=>collect(config('registration.categories'))->map(fn($name)=>(object)['name'=>$name]),'stats'=>['products'=>$b()->count(),'sellers'=>DB::table('users')->where('role','seller')->where('approval_status','approved')->where('active',1)->count(),'orders'=>DB::table('orders')->whereIn('status',['DELIVERED','COMPLETED'])->count()],'notice'=>DB::table('announcements')->orderByDesc('id')->first()]);}
 public function home(Request $r){
  // Old subcategory links still open their corresponding main category.
  if(!$r->filled('main_category')&&$r->filled('category')&&ctype_digit((string)$r->input('category'))){$main=DB::table('categories')->where('id',$r->input('category'))->value('main_category');if(in_array($main,config('registration.categories'),true))$r->merge(['main_category'=>$main]);}
  $products=\App\Services\Catalog::query($r)->paginate(12)->withQueryString();
  return view('shop',['products'=>$products,'mainCategories'=>config('registration.categories'),'categories'=>DB::table('categories')->whereNotNull('category_code')->when($r->filled('main_category'),fn($q)=>$q->where('main_category',$r->input('main_category')))->orderBy('id')->get()]);
 }

 public function product(int $id){$product=\App\Services\Commerce::catalog(DB::table('products'))->where('products.id',$id)->first();abort_unless($product,404);return view('product',['p'=>$product,'variations'=>DB::table('product_variations')->where('product_id',$id)->whereNull('deleted_at')->get()]);}
 public function cart(){return view('cart',['links'=>$this->links(),'rows'=>DB::table('cart_items as c')->join('products as p','p.id','=','c.product_id')->leftJoin('product_variations as v','v.id','=','c.variation_id')->where('c.buyer_id',auth()->id())->select('c.*','p.name','p.images','p.price','p.discount_percent','p.seller_id','v.name as variation','v.price_adjustment')->get(),'addresses'=>DB::table('addresses')->where('user_id',auth()->id())->get()]);}
 public function add(Request $r){\App\Services\Cart::add($r);return redirect('/cart')->with('success','Cart updated.');}
 public function address(Request $r){$v=$r->validate(['recipient'=>'required|max:150','phone'=>'required|max:40','street'=>'required|max:200','barangay'=>'required|max:120','city'=>'required|max:120','province'=>'required|max:120','postal_code'=>'nullable|max:20']);DB::table('addresses')->insert($v+['user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Address saved.');}
 public function checkout(Request $r){$ids=\App\Services\Commerce::checkout($r);return redirect('/orders')->with('success','Orders placed: '.implode(', ',$ids));}
 public function orders(){return view('orders',['links'=>$this->links(),'orders'=>DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')->where('o.buyer_id',auth()->id())->select('o.*','p.id as parcel_id','p.tracking_code','p.failure_reason','p.proof_path')->orderByDesc('o.id')->get()]);}
 public function confirm(Request $r,int $id){$p=DB::table('parcels')->where('order_id',$id)->first();abort_unless($p,404);Workflow::move($p->id,'COMPLETED',auth()->id());return back()->with('success','Order completed.');}
 public function rating(Request $r,int $id){$o=\App\Services\Support::ownsOrder($id);abort_unless($o->buyer_id==auth()->id(),403);if(!$r->filled('order_item_id')){$items=DB::table('order_items')->where('order_id',$id)->get();if($items->count()!==1)\App\Services\Support::fail('order_item_id','Select an order item to review on the Reviews page.');$r->merge(['order_item_id'=>$items[0]->id]);}abort_unless(DB::table('order_items')->where('id',$r->order_item_id)->where('order_id',$id)->exists(),403);return app(MarketController::class)->review($r);}
}
