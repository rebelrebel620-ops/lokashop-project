<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProductReviewWorkspaceController extends Controller
{
 public function index(Request $r){
  $seller=auth()->user()->role==='seller';abort_unless($seller||auth()->user()->role==='buyer',403);
  $tab=(!$seller&&$r->query('tab')==='pending')?'pending':'reviews';
  $base=DB::table('item_reviews as r')->join('order_items as i','i.id','=','r.order_item_id')
   ->join('orders as o','o.id','=','i.order_id')->leftJoin('users as b','b.id','=','r.buyer_id')
   ->where($seller?'r.seller_id':'r.buyer_id',auth()->id());
  $reviews=(clone $base)->select('r.*','i.name as product_name','i.image as product_image','i.product_id','i.quantity','o.status as order_status','o.completed_at','b.name as buyer_name')
   ->orderByDesc('r.id')->paginate(12)->withQueryString();
  $eligible=DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->where('o.buyer_id',auth()->id())
   ->where('o.status','COMPLETED')->whereNotNull('o.completed_at')->where('o.completed_at','>=',now()->subDays(30));
  $pending=(clone $eligible)->whereNotExists(fn($q)=>$q->selectRaw('1')->from('item_reviews as r')->whereColumn('r.order_item_id','i.id'));
  $counts=['reviews'=>(clone $base)->count(),'pending'=>$seller?0:(clone $pending)->count()];
  $purchases=(clone $pending)->select('i.*','o.completed_at')->orderByDesc('i.id')->paginate(12,['*'],'pending_page')->withQueryString();
  $selected=null;$editing=null;
  if(!$seller&&$r->filled('write')){
   $r->validate(['write'=>'integer|min:1']);$selected=(clone $eligible)->where('i.id',$r->integer('write'))->select('i.*','o.completed_at')->first();
   abort_unless($selected,404);$editing=DB::table('item_reviews')->where('order_item_id',$selected->id)->where('buyer_id',auth()->id())->first();
   abort_if($editing&&$editing->edit_count>=1,422,'This review has already been edited once.');
  }
  return view('reviews',['links'=>app(CommonController::class)->links(),'seller'=>$seller,'tab'=>$tab,'reviews'=>$reviews,'purchases'=>$purchases,'counts'=>$counts,'selected'=>$selected,'editing'=>$editing]);
 }
}
