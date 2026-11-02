<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class BuyerOrders
{
    public const CLOSED = ['DELIVERED','COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED'];

    public static function bucket(string $status): string
    {
        if ($status === 'PLACED') return 'pending';
        return in_array($status, self::CLOSED, true) ? 'completed' : 'receive';
    }

    public static function label(string $status): string
    {
        return [
            'PLACED'=>'Waiting for seller acceptance', 'CONFIRMED'=>'Seller accepted your order',
            'PREPARING'=>'Seller is preparing your parcel', 'READY_FOR_PICKUP'=>'Parcel is ready for pickup',
            'PICKED_UP'=>'Rider picked up your parcel', 'AT_SORTING_CENTER'=>'Parcel arrived at a sorting center',
            'SORTED'=>'Parcel sorted for its next journey', 'TRANSFER_OFFERED'=>'Driver assigned for sorting center transfer',
            'IN_TRANSIT_CENTER'=>'Parcel departed for the next sorting center',
            'ASSIGNED_TO_RIDER'=>'Local delivery rider assigned', 'OUT_FOR_DELIVERY'=>'Rider is delivering your parcel',
            'DELIVERED'=>'Parcel delivered', 'COMPLETED'=>'Order receipt confirmed',
            'DELIVERY_FAILED'=>'Delivery attempt unsuccessful', 'RETURN_TO_SENDER'=>'Parcel is returning to the seller',
            'RETURNED'=>'Returned parcel awaiting seller receipt', 'RETURN_RECEIVED'=>'Seller received the returned parcel',
            'CANCELLED'=>'Order cancelled', 'REFUNDED'=>'Order refunded', 'LOST'=>'Parcel reported lost',
            'DAMAGED'=>'Parcel reported damaged',
        ][$status] ?? ucfirst(strtolower(str_replace('_', ' ', $status)));
    }

    public static function orders(int $buyer)
    {
        return DB::table('orders as o')->join('parcels as p','p.order_id','=','o.id')
            ->leftJoin('seller_businesses as s','s.user_id','=','o.seller_id')
            ->where('o.buyer_id',$buyer)->select('o.*','p.id as parcel_id','p.tracking_code',
                'p.failure_reason','p.proof_path','s.name as store_name')->orderByDesc('o.id');
    }

    public static function filter($query, string $tab)
    {
        if ($tab === 'pending') return $query->where('o.status','PLACED');
        if ($tab === 'completed') return $query->whereIn('o.status',self::CLOSED);
        return $query->where('o.status','!=','PLACED')->whereNotIn('o.status',self::CLOSED);
    }

    public static function timeline($order, $parcel)
    {
        $events = DB::table('status_history')->where('parcel_id',$parcel->id)
            ->orderBy('created_at')->orderBy('id')->get()->map(fn($event)=>[
                'status'=>$event->to_status, 'title'=>str_starts_with($event->reason??'','[Delivery update] ')?substr($event->reason,18):self::label($event->to_status),
                'detail'=>str_starts_with($event->reason??'','[Delivery update] ')?null:$event->reason, 'at'=>$event->created_at, 'sort'=>$event->id,
            ]);
        if (!$events->contains(fn($event)=>$event['status']==='PLACED')) {
            $events->prepend(['status'=>'PLACED','title'=>'Order placed','detail'=>'Your order was sent to the seller.',
                'at'=>$order->created_at,'sort'=>0]);
        }
        // Driver handover does not change parcel status, but is a real delivery milestone.
        foreach (DB::table('assignments')->where('parcel_id',$parcel->id)->where('kind','transfer')
            ->whereNotNull('handed_over_at')->get() as $assignment) {
            $events->push(['status'=>'DRIVER_HANDOVER','title'=>'Driver confirmed parcel handover',
                'detail'=>'The destination sorting center must separately confirm receipt.',
                'at'=>$assignment->handed_over_at,'sort'=>0]);
        }
        if (!$events->contains(fn($event)=>$event['status']===$order->status)) {
            // Legacy records may lack history. Show the actual latest update, not invented intermediate times.
            $events->push(['status'=>$order->status,'title'=>self::label($order->status),
                'detail'=>'Latest recorded order status.','at'=>$order->updated_at,'sort'=>PHP_INT_MAX]);
        }
        return $events->sort(function($a,$b){return strcmp((string)$a['at'],(string)$b['at']) ?: ($a['sort']<=>$b['sort']);})
            ->reverse()->values();
    }
}
