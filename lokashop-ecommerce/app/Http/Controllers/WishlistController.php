<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\{Catalog,Support};
class WishlistController extends Controller
{
 public function index(Request $r){
  abort_unless(auth()->user()->role==='buyer',403);
  $buyer=auth()->id();$tab=$r->query('tab')==='stores'?'stores':'products';
  $saved=DB::table('wishlists as w')->join('products as p','p.id','=','w.product_id')->where('w.buyer_id',$buyer);
  $following=DB::table('store_follows as f')->join('seller_businesses as s','s.user_id','=','f.seller_id')->where('f.buyer_id',$buyer);
  $counts=['products'=>(clone $saved)->count(),'stores'=>(clone $following)->count()];
  $products=(clone $saved)->leftJoin('seller_businesses as s','s.user_id','=','p.seller_id')
   ->select('p.*','s.name as store_name','s.vacation as store_vacation')->orderByDesc('w.id')->paginate(12,['*'],'page')->withQueryString();
  $available=Catalog::query(Request::create('/shop'))->whereIn('products.id',$products->pluck('id'))->pluck('products.id')->all();
  $followed=DB::table('store_follows')->where('buyer_id',$buyer)->pluck('seller_id')->all();
  $stores=(clone $following)->join('users as u','u.id','=','s.user_id')->select('s.*','u.active as seller_active','u.approval_status','u.account_status')->orderByDesc('f.id')->paginate(12,['*'],'page')->withQueryString();
  return view('wishlist',['links'=>app(CommonController::class)->links(),'tab'=>$tab,'counts'=>$counts,'products'=>$products,'available'=>$available,'followed'=>$followed,'stores'=>$stores]);
 }
 public static function save(Request $r,bool $store){
  abort_unless(auth()->user()->role==='buyer',403);
  $field=$store?'seller_id':'product_id';$table=$store?'store_follows':'wishlists';
  $v=$r->validate([$field=>'required|integer|min:1','enabled'=>'required|boolean']);$enabled=$r->boolean('enabled');
  DB::transaction(function()use($v,$field,$table,$store,$enabled){
   DB::table('users')->where('id',auth()->id())->lockForUpdate()->first();
   if($enabled){
    $ok=$store?DB::table('seller_businesses as s')->join('users as u','u.id','=','s.user_id')->where('s.user_id',$v[$field])->where('u.role','seller')->where('u.active',1)->where('u.account_status','active')->where('u.approval_status','approved')->exists():Catalog::query(Request::create('/shop'))->where('products.id',$v[$field])->exists();
    if(!$ok)Support::fail($field,$store?'This store is currently unavailable.':'This product is currently unavailable.');
   }
   $key=['buyer_id'=>auth()->id(),$field=>$v[$field]];
   if($enabled)DB::table($table)->insertOrIgnore($key+['created_at'=>now(),'updated_at'=>now()]);else DB::table($table)->where($key)->delete();
  });
  $message=$store?($enabled?'Store followed. Find it in Wishlist → Following stores.':'Store unfollowed.'):($enabled?'Product saved to your wishlist.':'Product removed from your wishlist.');
  return $r->expectsJson()?response()->json(['message'=>$message,'data'=>['enabled'=>$enabled,$field=>(int)$v[$field]]]):back()->with('success',$message);
 }
}
