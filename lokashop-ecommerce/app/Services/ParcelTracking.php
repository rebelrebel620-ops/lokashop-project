<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

class ParcelTracking
{
    // A history-only update: keep the actual parcel/order status unchanged.
    public static function note(int $parcelId, int $actor, string $message): void
    {
        $parcel=DB::table('parcels')->find($parcelId);
        if (!$parcel) return;
        DB::table('status_history')->insert(['parcel_id'=>$parcelId,
            'from_status'=>$parcel->status,'to_status'=>$parcel->status,'actor_id'=>$actor,
            'reason'=>'[Delivery update] '.$message,'created_at'=>now(),'updated_at'=>now()]);
    }
}
