<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\{Commerce,Catalog,Support};
class ProductStateController extends Controller
{
 public function show(int $id){
  $p=Commerce::catalog(DB::table('products'))->where('products.id',$id)->first();
  if(!$p)return response()->json(['available'=>false])->header('Cache-Control','no-store');
  return response()->json(['available'=>true,'price'=>Catalog::price($p),'stock'=>(int)$p->stock,
   'vacation'=>(bool)DB::table('seller_businesses')->where('user_id',$p->seller_id)->value('vacation'),
   'variations'=>DB::table('product_variations')->where('product_id',$id)->whereNull('deleted_at')->orderBy('id')->get()->map(fn($v)=>[
    'id'=>(int)$v->id,'price'=>Catalog::price($p,$v),'stock'=>(int)min($p->stock,$v->stock),
    'image'=>Support::productImageUrl($v->image,1200),
    'label'=>$v->name.' · '.collect(json_decode($v->options??'{}',true)?:[])->map(fn($value,$key)=>$key.': '.$value)->implode(' / ').' · ₱'.number_format(Catalog::price($p,$v),2).' ('.min($p->stock,$v->stock).' available)'
   ])->values()])->header('Cache-Control','no-store');
 }
}
