<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceRecords
{
    public static function cards($records): array
    {
        $rows=collect($records)->map(fn($r)=>(array)$r);
        $relations=['buyer_id'=>['users','name'],'seller_id'=>['users','name'],'rider_id'=>['users','name'],'user_id'=>['users','name'],'company_id'=>['users','name'],'product_id'=>['products','name'],'order_item_id'=>['order_items','name'],'center_id'=>['sorting_centers','name'],'delivery_area_id'=>['delivery_areas','name']];
        $names=[];
        foreach($relations as $field=>[$table,$label]){$ids=$rows->pluck($field)->filter()->unique()->all();if($ids)$names[$field]=DB::table($table)->whereIn('id',$ids)->pluck($label,'id')->all();}
        $cards=[];
        foreach($rows as $row){
            $fields=[];
            foreach($row as $key=>$value){
                if(in_array($key,['id','photos','address_snapshot','municipality_key','profile_photo','edit_count','deleted_at','created_at','updated_at','name','tracking_code','code','status','reference'])||preg_match('/password|token|hash|secret|proof_path|document|attachment/i',$key))continue;
                $label=ucwords(str_replace('_',' ',preg_replace('/_id$/','',$key)));
                if(isset($relations[$key]))$value=$value?($names[$key][$value]??'Unavailable'):null;
                elseif($key==='order_id')$value=$value?'Order #'.$value:null;
                elseif(is_array($value)||is_object($value))$value=implode(', ',array_map(fn($v)=>is_scalar($v)?(string)$v:'', (array)$value));
                elseif(is_numeric($value)&&in_array($key,['amount','base_fee','extra_kg_fee','earnings','total','price','minimum_spend']))$value='₱'.number_format((float)$value,2);
                elseif(is_string($value)&&str_ends_with($key,'_at')&&$value){try{$value=\Illuminate\Support\Carbon::parse($value)->format('M j, Y · g:i A');}catch(\Throwable $error){/* Retain non-date text. */}}
                elseif(in_array($key,['active','online','visible','claimable']))$value=$value?'Yes':'No';
                elseif(is_string($value)&&preg_match('/^\[.*\]$/s',$value)){$array=json_decode($value,true);if(is_array($array))$value=implode(', ',array_filter($array,'is_scalar'));}
                if($value===null||$value==='')continue;
                $fields[$label]=(string)$value;
            }
            $cards[]=['title'=>$row['name']??$row['tracking_code']??$row['code']??$row['product_name']??$row['reference']??(isset($row['order_id'])?'Order #'.$row['order_id']:'Activity'), 'id'=>$row['id']??null,'status'=>$row['status']??null,'date'=>$row['created_at']??null,'fields'=>$fields,'source'=>$row];
        }
        return $cards;
    }
}
