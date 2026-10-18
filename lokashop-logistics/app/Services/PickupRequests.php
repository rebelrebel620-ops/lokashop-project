<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PickupRequests
{
    public static function deadline(object $assignment): Carbon
    {
        // The twelve hours start at the seller request, not center verification.
        if ($assignment->created_at) return Carbon::parse($assignment->created_at)->addHours(12);
        return $assignment->expires_at ? Carbon::parse($assignment->expires_at) : now()->addHours(12);
    }

    public static function refresh(?int $parcelId = null, ?int $sellerId = null): void
    {
        $query = DB::table('assignments as s')->join('parcels as p','p.id','=','s.parcel_id')
            ->join('orders as o','o.id','=','p.order_id')->where('s.kind','pickup')
            ->whereIn('s.status',['requested','offered','accepted'])
            ->whereIn('p.status',['PREPARING','READY_FOR_PICKUP'])->select('s.*');
        if ($parcelId !== null) $query->where('p.id',$parcelId);
        if ($sellerId !== null) $query->where('o.seller_id',$sellerId);
        foreach ($query->get() as $candidate) {
            DB::transaction(function () use ($candidate) {
                $parcel=DB::table('parcels')->where('id',$candidate->parcel_id)->lockForUpdate()->first();
                $a=DB::table('assignments')->where('id',$candidate->id)->lockForUpdate()->first();
                if (!$parcel || !$a || !in_array($parcel->status,['PREPARING','READY_FOR_PICKUP']) || !in_array($a->status,['requested','offered','accepted'])) return;
                $deadline=self::deadline($a);
                if (now()->lt($deadline)) {
                    if (!$a->expires_at || !Carbon::parse($a->expires_at)->equalTo($deadline)) DB::table('assignments')->where('id',$a->id)->update(['expires_at'=>$deadline,'updated_at'=>now()]);
                    return;
                }
                DB::table('assignments')->where('id',$a->id)->update(['status'=>'expired','expires_at'=>$deadline,'updated_at'=>now()]);
                $order=DB::table('orders')->find($parcel->order_id);
                Support::notify([$order->seller_id],"Order #{$order->id}: Pickup unsuccessful. Your 12-hour pickup request expired before collection. Open Seller Orders and request another rider.");
                Support::notify([$a->rider_id],"Pickup request for {$parcel->tracking_code} expired. Do not collect this parcel under the expired assignment.");
            });
        }
    }

    public static function request(int $orderId, int $sellerId, int $riderId): void
    {
        $existing=DB::table('parcels')->where('order_id',$orderId)->first();
        $order=DB::table('orders')->where('id',$orderId)->where('seller_id',$sellerId)->first();
        abort_unless($existing && $order,403);
        self::refresh($existing->id);
        DB::transaction(function () use ($existing,$sellerId,$riderId) {
            $p=DB::table('parcels')->where('id',$existing->id)->lockForUpdate()->first();
            abort_unless(in_array($p->status,['PREPARING','READY_FOR_PICKUP']),422,'This order is not waiting for pickup.');
            if (DB::table('assignments')->where('parcel_id',$p->id)->where('kind','pickup')->whereIn('status',['requested','offered','accepted'])->exists()) Support::fail('pickup','A pickup request is still active. Wait for collection, decline or the 12-hour expiry before requesting again.');
            $rider=DB::table('users')->find($riderId);
            Municipalities::courier($rider,$p,'pickup');
            DB::table('parcels')->where('id',$p->id)->update(['center_id'=>null,'delivery_area_id'=>null,'updated_at'=>now()]);
            DB::table('assignments')->insert(['parcel_id'=>$p->id,'rider_id'=>$riderId,'kind'=>'pickup','status'=>'requested','expires_at'=>now()->addHours(12),'earnings'=>30,'created_at'=>now(),'updated_at'=>now()]);
            Support::notify([$rider->company_id],"New seller pickup request for {$p->tracking_code}. Verify it before its 12-hour deadline.");
        });
    }
}
