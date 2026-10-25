<?php
namespace App\Services;

use Illuminate\Support\Facades\{DB,Schema};

class AdminUserDetails
{
    public static function forAccount(int $id, bool $logistics): array
    {
        $user=DB::table('users')->where('id',$id)
            ->whereIn('role',$logistics?['sorting_center','rider']:['buyer','seller'])
            ->first(['id','name','email','phone','role','courier_type','approval_status','account_status','active','created_at','last_seen_at','email_verified_at','profile_photo','company_id','vehicle','plate_number','license_expires_at']);
        abort_unless($user,404);
        $role=$user->role==='rider'?($user->courier_type==='driver'?'Driver':'Rider'):ucwords(str_replace('_',' ',$user->role));
        $facts=[
            'Account ID'=>$user->id,'Account type'=>$role,'Email'=>$user->email,'Phone'=>$user->phone,
            'Approval'=>self::label($user->approval_status),
            'Email verification'=>$user->email_verified_at?'Verified':'Not verified',
            'Account status'=>self::label($user->account_status).(!$user->active?' / Disabled':''),
            'Joined'=>$user->created_at,'Last seen'=>$user->last_seen_at,
        ];
        $address=DB::table('addresses')->where('user_id',$id)->orderByDesc('is_default')->orderBy('id')->first(['street','barangay','city','province','postal_code']);
        $stats=[];$sections=[];$areas=collect();$areaCount=0;$trips=collect();
        if($user->role==='buyer'){
            $orders=DB::table('orders')->where('buyer_id',$id);
            $stats=['Orders placed'=>(clone $orders)->count(),'Successful deliveries'=>(clone $orders)->whereIn('status',['DELIVERED','COMPLETED'])->count(),'Completed orders'=>(clone $orders)->where('status','COMPLETED')->count(),'Cancelled orders'=>(clone $orders)->where('status','CANCELLED')->count()];
            $sections[]=['title'=>'Shopping activity','facts'=>['Return requests'=>DB::table('return_requests')->where('buyer_id',$id)->count(),'Product reviews'=>DB::table('item_reviews')->where('buyer_id',$id)->count()]];
        }elseif($user->role==='seller'){
            $store=DB::table('seller_businesses')->where('user_id',$id)->first(['name','category','description','vacation','created_at']);
            $products=DB::table('products')->where('seller_id',$id)->whereNull('deleted_at');
            $orders=DB::table('orders')->where('seller_id',$id);
            $stats=['Products'=>(clone $products)->count(),'Published products'=>Commerce::catalog(clone $products)->count(),'Orders received'=>(clone $orders)->count(),'Completed orders'=>(clone $orders)->where('status','COMPLETED')->count()];
            $rating=DB::table('item_reviews')->where('seller_id',$id)->avg('stars');
            $sections[]=['title'=>'Store details','facts'=>$store?['Store name'=>$store->name,'Main category'=>$store->category,'Description'=>$store->description,'Store mode'=>$store->vacation?'On vacation':'Open','Registered'=>$store->created_at,'Product rating'=>$rating===null?'Not rated yet':number_format($rating,1).' / 5']:['Store'=>'No registered store details yet']];
        }elseif($user->role==='sorting_center'){
            $center=DB::table('sorting_centers')->where('user_id',$id)->first(['id','name','city','province','created_at']);
            $team=DB::table('users')->where('company_id',$id)->where('role','rider');
            $stats=['Local riders'=>(clone $team)->where('courier_type','rider')->count(),'Drivers'=>(clone $team)->where('courier_type','driver')->count(),'Active center parcels'=>$center?DB::table('parcels')->where('center_id',$center->id)->whereNotIn('status',['DELIVERED','COMPLETED','CANCELLED','REFUNDED','RETURN_RECEIVED','RETURNED'])->count():0,'Service areas'=>$center?DB::table('delivery_areas')->where('center_id',$center->id)->count():0];
            $sections[]=['title'=>'Sorting center details','facts'=>$center?['Center name'=>$center->name,'Municipality / city'=>$center->city,'Province'=>$center->province,'Registered'=>$center->created_at]:['Center'=>'No registered sorting center yet']];
            if($center){
                $areaQuery=DB::table('delivery_areas as a')->leftJoin('users as r','r.id','=','a.rider_id')->where('a.center_id',$center->id);
                $areaCount=(clone $areaQuery)->count();
                $areas=$areaQuery->orderBy('a.name')->limit(12)->get(['a.name','a.barangay','a.city','a.province','r.name as rider_name']);
            }
        }else{
            $center=$user->company_id?DB::table('sorting_centers')->where('user_id',$user->company_id)->first(['id','name','city','province']):null;
            $sections[]=['title'=>'Courier and service details','facts'=>['Sorting center'=>$center->name??'Not attached','Service municipality'=>$center?trim($center->city.', '.$center->province,', '):null,'Vehicle'=>$user->vehicle,'Plate number'=>$user->plate_number,'License valid until'=>$user->license_expires_at]];
            $assignments=DB::table('assignments')->where('rider_id',$id);
            if($user->courier_type==='driver'){
                $tripQuery=Schema::hasTable('driver_trips')?DB::table('driver_trips')->where('driver_id',$id):null;
                $stats=['Transport trips'=>$tripQuery?(clone $tripQuery)->count():0,'Active trips'=>$tripQuery?(clone $tripQuery)->whereIn('status',['accepted','in_transit'])->count():0,'Completed trips'=>$tripQuery?(clone $tripQuery)->where('status','completed')->count():0,'Parcels transported'=>(clone $assignments)->where('kind','transfer')->where('status','completed')->distinct()->count('parcel_id')];
                if($tripQuery){
                    $trips=DB::table('driver_trips as t')->leftJoin('sorting_centers as c','c.id','=','t.origin_center_id')->where('t.driver_id',$id)->orderByDesc('t.id')->limit(8)->get(['t.id','t.status','t.created_at','t.departed_at','t.completed_at','c.name as origin_name']);
                    $counts=DB::table('driver_trip_items')->whereIn('trip_id',$trips->pluck('id'))->selectRaw('trip_id, COUNT(*) as total')->groupBy('trip_id')->pluck('total','trip_id');
                    $destinations=DB::table('driver_trip_stops as s')->join('sorting_centers as c','c.id','=','s.center_id')->whereIn('s.trip_id',$trips->pluck('id'))->orderBy('s.position')->get(['s.trip_id','c.name'])->groupBy('trip_id');
                    foreach($trips as $trip){$trip->parcel_count=$counts[$trip->id]??0;$trip->destinations=($destinations[$trip->id]??collect())->pluck('name')->implode(' → ');}
                }
            }else{
                $stats=['Deliveries completed'=>(clone $assignments)->where('kind','delivery')->where('status','completed')->distinct()->count('parcel_id'),'Pickups completed'=>(clone $assignments)->where('kind','pickup')->where('status','completed')->distinct()->count('parcel_id'),'Active jobs'=>(clone $assignments)->where('status','accepted')->count(),'Failed delivery attempts'=>(clone $assignments)->where('kind','delivery')->where('status','failed')->count()];
                $areaQuery=DB::table('delivery_areas as a')->join('sorting_centers as c','c.id','=','a.center_id')->where('a.rider_id',$id)->where('c.user_id',$user->company_id);
                $areaCount=(clone $areaQuery)->count();
                $areas=$areaQuery->orderBy('a.name')->limit(12)->get(['a.name','a.barangay','a.city','a.province']);
            }
        }
        return [
            'id'=>$user->id,'name'=>$user->name,'role'=>$role,'photo'=>Support::imageUrl($user->profile_photo),
            'facts'=>$facts,'address'=>$address?implode(', ',array_filter((array)$address,fn($v)=>$v!==null&&$v!=='')):null,
            'stats'=>$stats,'sections'=>$sections,'areas'=>$areas,'areaCount'=>$areaCount,'trips'=>$trips,
            'showAreas'=>$user->role==='sorting_center'||($user->role==='rider'&&$user->courier_type!=='driver'),
            'showTrips'=>$user->role==='rider'&&$user->courier_type==='driver',
        ];
    }
    private static function label(?string $value):string {return ucwords(str_replace('_',' ',$value??''));}
}
