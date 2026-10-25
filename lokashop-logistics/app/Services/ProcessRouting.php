<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ProcessRouting {
 public static function local($p):bool {
  return Municipalities::matches(DB::table('sorting_centers')->find($p->center_id),AreaRouting::address($p));
 }
 public static function destination($p){return Municipalities::centerFor(AreaRouting::address($p));}
 public static function paginate($rows,int $perPage=20){$page=max(1,(int)request('page',1));return new \Illuminate\Pagination\LengthAwarePaginator($rows->forPage($page,$perPage)->values(),$rows->count(),$perPage,$page,['path'=>request()->url(),'query'=>request()->query()]);}
}
