<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ProductCategories {
 public static function seller($id){$main=DB::table('seller_businesses')->where('user_id',$id)->value('category');return DB::table('categories')->whereNotNull('category_code')->where('main_category',$main)->orderBy('id');}
 public static function validate($id):void {if(!self::seller(auth()->id())->where('id',$id)->exists())Support::fail('category_id','Choose a subcategory of your registered main category. Legacy sellers must ask the administrator to assign a main category before editing products.');}
}
