<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\{Wallet,WalletEarnings,WalletAutomation,Support};
class WalletController extends Controller {
 public function index(Request $r){
  WalletAutomation::syncUser(auth()->id());$role=auth()->user()->role;
  $data=['center_owed'=>$role==='sorting_center'?(int)WalletEarnings::unsettled()->where('center_user_id',auth()->id())->sum('amount_cents'):0,'courier_payments'=>in_array($role,['sorting_center','rider'],true)?DB::table('wallet_courier_payments')->where($role==='sorting_center'?'center_user_id':'courier_user_id',auth()->id())->orderByDesc('created_at')->paginate(15,['*'],'payments_page'):null,'balance'=>number_format(Wallet::balance(auth()->id())/100,2,'.',''),'demo'=>true,'transactions'=>DB::table('wallet_entries')->where('user_id',auth()->id())->orderByDesc('id')->paginate(20)];
  if($data['courier_payments'])$data['courier_payments']->getCollection()->transform(function($p){$p->center_settled=($p->funding_source??'center')==='shipping_pool'||DB::table('wallet_entries')->where('reference','courier-pay:'.$p->assignment_id)->exists();return $p;});
  return ($r->is('api/*')||$r->expectsJson())?response()->json($data):view('wallet',$data+['links'=>app(CommonController::class)->links()]);
 }
 public function transfer(Request $r){
  $v=$r->validate(['recipient'=>'required|email|max:190','amount'=>'required|numeric|decimal:0,2|min:1|max:100000','password'=>'required|current_password']);
  Support::once($r,'wallet-transfer',function()use($r,$v){$to=DB::table('users')->whereRaw('LOWER(email) = ?',[strtolower(trim($v['recipient']))])->value('id');if(!$to)Support::fail('recipient','No active wallet user matches that email.');Wallet::transfer(auth()->id(),(int)$to,Wallet::cents($v['amount']),$r->header('Idempotency-Key')??$r->input('_request_key'));return true;});
  $center=DB::table('users')->whereRaw('LOWER(email) = ?',[strtolower(trim($v['recipient']))])->where('role','sorting_center')->value('id');if($center)WalletEarnings::payPending((int)$center);
  return ($r->is('api/*')||$r->expectsJson())?response()->json(['message'=>'Transfer completed.','balance'=>number_format(Wallet::balance(auth()->id())/100,2,'.','')]):redirect('/wallet')->with('success','Transfer completed.');
 }
}
