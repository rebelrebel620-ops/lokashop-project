<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class CodSettlement {
 public static function eligible($q,string $order='orders') {
  return $q->where(function($q)use($order){$q->whereNotExists(fn($p)=>$p->selectRaw('1')->from('payments as codpay')->whereColumn('codpay.order_id',$order.'.id')->where('codpay.method','cod'))->orWhereExists(fn($r)=>$r->selectRaw('1')->from('cod_collections as cc')->join('parcels as cp','cp.id','=','cc.parcel_id')->join('remittances as cr','cr.id','=','cc.remittance_id')->whereColumn('cp.order_id',$order.'.id')->whereColumn('cc.rider_id','cr.rider_id')->whereColumn('cc.company_id','cr.company_id')->where('cr.status','confirmed')->whereColumn('cr.amount','>=','cr.expected')->where('cr.expected','>',0));});
 }
 public static function ready(int $order): bool {return self::eligible(DB::table('orders')->where('id',$order))->exists();}
}
