<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\{Support,VoucherDisplay};
class VoucherWorkspaceController extends Controller
{
 public function buyer(Request $r){
  abort_unless(auth()->user()->role==='buyer',403);$buyer=auth()->id();
  $tab=in_array($r->query('tab'),['lokashop','shipping','store','claimed'],true)?$r->query('tab'):'all';
  $q=DB::table('vouchers as v')->leftJoin('seller_businesses as s','s.user_id','=','v.seller_id')
   ->where(function($q)use($buyer){$q->where(function($q){$q->where('v.active',1)->where('v.claimable',1)->where(fn($q)=>$q->whereNull('v.expires_at')->orWhere('v.expires_at','>',now()));})->orWhereExists(fn($q)=>$q->selectRaw('1')->from('voucher_claims as c')->whereColumn('c.voucher_id','v.id')->where('c.buyer_id',$buyer));});
  if($tab==='lokashop')$q->whereNull('v.seller_id')->where('v.type','!=','shipping');
  if($tab==='shipping')$q->whereNull('v.seller_id')->where('v.type','shipping');
  if($tab==='store')$q->whereNotNull('v.seller_id');
  if($tab==='claimed')$q->whereExists(fn($q)=>$q->selectRaw('1')->from('voucher_claims as c')->whereColumn('c.voucher_id','v.id')->where('c.buyer_id',$buyer));
  $vouchers=$q->select('v.*','s.name as store_name','s.logo as store_logo')->orderByDesc('v.id')->paginate(12)->withQueryString();
  $claims=DB::table('voucher_claims')->where('buyer_id',$buyer)->whereIn('voucher_id',$vouchers->pluck('id'))->pluck('voucher_id')->all();
  $uses=DB::table('voucher_redemptions')->where('buyer_id',$buyer)->whereIn('voucher_id',$vouchers->pluck('id'))->selectRaw('voucher_id, COUNT(*) as total')->groupBy('voucher_id')->pluck('total','voucher_id');
  return view('buyer-vouchers',['links'=>app(CommonController::class)->links(),'vouchers'=>$vouchers,'claims'=>$claims,'uses'=>$uses,'tab'=>$tab]);
 }
 public function manage(Request $r){
  $admin=auth()->user()->role==='admin';abort_unless($admin||auth()->user()->role==='seller',403);
  $q=DB::table('vouchers')->where('seller_id',$admin?null:auth()->id());
  $editing=null;$locked=false;
  if($r->filled('edit')){$r->validate(['edit'=>'integer|min:1']);$editing=(clone $q)->where('id',$r->integer('edit'))->first();abort_unless($editing,404);$locked=DB::table('voucher_claims')->where('voucher_id',$editing->id)->exists();}
  return view('voucher-manager',['links'=>app(CommonController::class)->links(),'vouchers'=>$q->orderByDesc('id')->paginate(10)->withQueryString(),'voucher'=>$editing,'locked'=>$locked,'admin'=>$admin]);
 }
 public function claim(Request $r){
  abort_unless(auth()->user()->role==='buyer',403);$v=$r->validate(['voucher_id'=>'required|integer|min:1']);
  DB::transaction(function()use($v){$voucher=DB::table('vouchers')->where('id',$v['voucher_id'])->lockForUpdate()->first();abort_unless($voucher,404);
   $used=DB::table('voucher_redemptions')->where('buyer_id',auth()->id())->where('voucher_id',$voucher->id)->count();
   $state=VoucherDisplay::state($voucher,$used);if($state!=='Available')Support::fail('voucher_id',$state.'. Choose another voucher.');
   if(!$voucher->claimable)Support::fail('voucher_id','This voucher is code-only and cannot be claimed.');
   DB::table('voucher_claims')->insertOrIgnore(['buyer_id'=>auth()->id(),'voucher_id'=>$voucher->id,'created_at'=>now(),'updated_at'=>now()]);
  });
  $message='Voucher claimed. Select its code at checkout; minimum spend and usage limits still apply.';
  return $r->expectsJson()?response()->json(['message'=>$message]):back()->with('success',$message);
 }
}
