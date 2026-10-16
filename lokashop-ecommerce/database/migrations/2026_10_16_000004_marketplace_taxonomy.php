<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasColumn('categories','main_category'))Schema::table('categories',function(Blueprint $t){$t->string('main_category')->nullable()->index();$t->string('category_code')->nullable()->unique();});
  DB::transaction(function(){
   $i=0;foreach(config('registration.tree') as $main=>$children){$i++;foreach($children as $j=>$name){$code=$i.'.'.($j+1);DB::table('categories')->updateOrInsert(['category_code'=>$code],['name'=>$code.' '.$name,'main_category'=>$main,'created_at'=>now(),'updated_at'=>now()]);}}
   // Only unambiguous old business categories are mapped automatically.
   $aliases=['Home'=>'Home and Garden','Home & Living'=>'Home and Garden','Mobiles & Gadgets'=>'Electronics and Gadgets','Beauty & Personal Care'=>'Health and Beauty','Food & Groceries'=>'Food and Gourmet','Sports & Outdoors'=>'Sports and Outdoors','Toys & Games'=>'Kids and Baby'];
   foreach($aliases as $old=>$new)DB::table('seller_businesses')->where('category',$old)->update(['category'=>$new]);
   // Existing listings retain their IDs/history. Legacy categories require seller review.
  });
 }
 public function down(): void { /* Retain taxonomy and all existing product references. */ }
};
