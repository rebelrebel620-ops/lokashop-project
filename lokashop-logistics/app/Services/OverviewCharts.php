<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class OverviewCharts {
 public static function data(): array {
  $u=auth()->user();$role=$u->role;$noun='orders';$title='Order activity';
  if(in_array($role,['buyer','seller','admin'])){
   $q=DB::table('orders as o');if($role!=='admin')$q->where('o.'.$role.'_id',$u->id);
   $segments=[];foreach(['pending'=>'New orders','receive'=>'Processing & delivery','completed'=>'Delivered & completed','returns'=>'Returns & cancelled'] as $key=>$label)$segments[]=['label'=>$label,'value'=>BuyerOrders::filter(clone $q,$key)->count()];$date='o.created_at';
  }elseif($role==='rider'){
   $driver=$u->courier_type==='driver';$q=DB::table($driver?'driver_trips':'assignments')->where($driver?'driver_id':'rider_id',$u->id);if(!$driver)$q->whereNull('trip_id');$noun=$driver?'trips':'assignments';$title=$driver?'Trip activity':'Assignment activity';$date='created_at';$segments=self::statuses($q);
  }else{
   $q=DB::table('parcels');if($role==='sorting_center'){$center=DB::table('sorting_centers')->where('user_id',$u->id)->value('id');$q->where('center_id',$center??0);}elseif($role!=='logistics_admin')$q->whereRaw('1=0');
   if(request()->filled('from'))$q->whereDate('created_at','>=',request('from'));if(request()->filled('to'))$q->whereDate('created_at','<=',request('to'));
   $noun='parcels';$title='Parcel activity';$date='created_at';$segments=self::statuses($q);
  }
  $rows=(clone $q)->where($date,'>=',today()->subDays(6))->where($date,'<',today()->addDay())->selectRaw('DATE('.$date.') as day, COUNT(*) as total')->groupBy('day')->pluck('total','day');
  $days=[];foreach(range(6,0) as $ago){$day=today()->subDays($ago);$days[]=['label'=>$day->format('M j'),'value'=>(int)($rows[$day->toDateString()]??0)];}
  return compact('segments','days','noun','title');
 }
 private static function statuses($q):array {
  $rows=(clone $q)->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderByDesc('total')->orderBy('status')->get();$segments=[];
  foreach($rows->take(5) as $row)$segments[]=['label'=>ucfirst(strtolower(str_replace('_',' ',$row->status))),'value'=>(int)$row->total];
  if($rows->count()>5)$segments[]=['label'=>'Other statuses','value'=>(int)$rows->slice(5)->sum('total')];return $segments;
 }
}
