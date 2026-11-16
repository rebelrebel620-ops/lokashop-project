<?php
// Test fixture only: represent cash physically remitted and confirmed by its center.
function confirmTestCod(int $order, int $rider, int $company): void {
 $db=\Illuminate\Support\Facades\DB::class;
 $parcel=$db::table('parcels')->where('order_id',$order)->first();
 $amount=(float)$db::table('payments')->where('order_id',$order)->value('amount');
 $remit=$db::table('remittances')->insertGetId(['rider_id'=>$rider,'company_id'=>$company,'expected'=>$amount,'amount'=>$amount,'reference'=>'FIXTURE-COD-'.$order,'status'=>'confirmed','created_at'=>now(),'updated_at'=>now()]);
 $db::table('cod_collections')->updateOrInsert(['parcel_id'=>$parcel->id],['rider_id'=>$rider,'company_id'=>$company,'amount'=>$amount,'remittance_id'=>$remit,'created_at'=>now(),'updated_at'=>now()]);
}
