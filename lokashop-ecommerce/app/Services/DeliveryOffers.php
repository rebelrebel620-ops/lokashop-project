<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DeliveryOffers
{
    public static function deadline(object $assignment): Carbon
    {
        if ($assignment->created_at) return Carbon::parse($assignment->created_at)->addHours(12);
        return $assignment->expires_at ? Carbon::parse($assignment->expires_at) : now()->addHours(12);
    }

    public static function refresh(?int $parcelId = null): void
    {
        $query=DB::table('assignments')->where('kind','delivery')->where('status','offered');
        if ($parcelId !== null) $query->where('parcel_id',$parcelId);
        foreach ($query->get() as $candidate) {
            DB::transaction(function () use ($candidate) {
                $p=DB::table('parcels')->where('id',$candidate->parcel_id)->lockForUpdate()->first();
                $a=DB::table('assignments')->where('id',$candidate->id)->lockForUpdate()->first();
                if (!$p || !$a || $a->status!=='offered' || $p->status!=='ASSIGNED_TO_RIDER') return;
                $deadline=self::deadline($a);
                if (now()->lt($deadline)) {
                    if (!$a->expires_at || !Carbon::parse($a->expires_at)->equalTo($deadline)) DB::table('assignments')->where('id',$a->id)->update(['expires_at'=>$deadline,'updated_at'=>now()]);
                    return;
                }
                DB::table('assignments')->where('id',$a->id)->update(['status'=>'expired','expires_at'=>$deadline,'updated_at'=>now()]);
                $company=DB::table('sorting_centers')->where('id',$p->center_id)->value('user_id');
                Support::notify([$company],"Delivery offer for {$p->tracking_code} expired after 12 hours without acceptance. Open Parcels to resend the delivery offer.");
            });
        }
    }
}
