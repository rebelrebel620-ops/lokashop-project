<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Auth};
use Illuminate\Validation\ValidationException;
use App\Services\{Conversations,WorkspaceRecords,Catalog};
$before=$n;
$controller=new App\Http\Controllers\CommonController();
loginAs($buyer);$r=requestFor(['with'=>$seller]);$page=Conversations::page($r);ok($page['selected']->id===$seller,'Buyer selects allowed seller conversation');ok(!$page['users']->contains('id',$rider),'Buyer does not see unrelated logistics recipients');
DB::table('messages')->insert([
 ['sender_id'=>$buyer,'recipient_id'=>$seller,'body'=>'Buyer seller private thread','created_at'=>now(),'updated_at'=>now()],
 ['sender_id'=>$other,'recipient_id'=>$seller,'body'=>'Another buyers private text','created_at'=>now(),'updated_at'=>now()],
 ['sender_id'=>$seller,'recipient_id'=>$buyer,'body'=>'Seller reply here','created_at'=>now(),'updated_at'=>now()]
]);
$page=Conversations::page(requestFor(['with'=>$seller]));ok($page['messages']->count()===2,'Conversation shows only selected two-person thread');ok(!$page['messages']->contains('body','Another buyers private text'),'Other buyer message is excluded');
$html=$controller->messages(requestFor(['with'=>$seller]))->render();ok(str_contains($html,'Seller reply here')&&!str_contains($html,'Another buyers private text'),'Messages render named thread without leaking another buyer');ok(str_contains($html,'name="recipient_id" value="'.$seller.'"'),'Composer submits selected recipient');
try{Conversations::page(requestFor(['with'=>$rider]));throw new RuntimeException('Unrelated courier selectable');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===404,'Unrelated recipient cannot be opened by editing query');}
loginAs($rider);$page=Conversations::page(requestFor(['with'=>$company]));ok($page['selected']->id===$company,'Courier can select own sorting center');
$secondCompany=user('sorting_center','unrelatedcenter');try{Conversations::page(requestFor(['with'=>$secondCompany]));throw new RuntimeException('Other center selectable');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===404,'Courier contact list respects company restriction');}
foreach([$buyer,$seller] as $uid){loginAs($uid);requestFor();$html=$controller->profile()->render();ok(str_contains($html,'Personal details')&&str_contains($html,'Account address'),'Account sections render for '.$uid);}
loginAs($rider);requestFor();$html=$controller->profile()->render();ok(str_contains($html,'Personal details'),'Rider account renders');
$cards=WorkspaceRecords::cards(collect([(object)['id'=>101,'rider_id'=>$rider,'amount'=>50,'password'=>'SECRET','api_token'=>'PRIVATE','address_snapshot'=>'PRIVATE ADDRESS','created_at'=>now()->toDateTimeString()]]));ok(!str_contains(json_encode($cards[0]['fields']),'SECRET')&&!str_contains(json_encode($cards[0]['fields']),'PRIVATE'),'Cards hide authentication and address snapshot fields');ok($cards[0]['fields']['Rider']==='rider','Record foreign key resolves to user name');
if(config('app.site_kind')==='ecommerce'){
 loginAs($buyer);$pet=DB::table('categories')->where('main_category','Pet Supplies')->orderBy('id')->first();$pet2=DB::table('categories')->where('main_category','Pet Supplies')->where('id','!=',$pet->id)->first();$electronic=DB::table('categories')->where('main_category','Electronics and Gadgets')->first();
 foreach([[$pet->id,'Category pet one'],[$pet2->id,'Category pet two'],[$electronic->id,'Category laptop']] as [$catId,$name])DB::table('products')->insert(['seller_id'=>$seller,'category_id'=>$catId,'name'=>$name,'price'=>100,'stock'=>5,'approved'=>1,'active'=>1]);
 $rows=Catalog::query(requestFor(['main_category'=>'Pet Supplies']))->get();ok($rows->count()===2,'Main category returns products across its subcategories');$rows=Catalog::query(requestFor(['main_category'=>'Pet Supplies','category'=>$pet->id]))->get();ok($rows->count()===1&&$rows[0]->name==='Category pet one','Subcategory narrows the parent category products');
 try{Catalog::query(requestFor(['main_category'=>'Pet Supplies','category'=>$electronic->id]));throw new RuntimeException('Mismatched subcategory accepted');}catch(ValidationException $ex){ok(isset($ex->errors()['category']),'Subcategory must belong to selected main category');}
 $shop=new App\Http\Controllers\ShopController();$html=$shop->home(requestFor())->render();ok(str_contains($html,'Main category')&&!str_contains($html,'id="shop-subcategory"'),'First shop filter shows main categories before subcategories');
 $html=$shop->home(requestFor(['main_category'=>'Pet Supplies']))->render();ok(str_contains($html,'id="shop-subcategory"')&&str_contains($html,'Category pet one')&&!str_contains($html,'Category laptop'),'Selecting parent renders child filter and matching products');
 $request=requestFor(['category'=>$pet->id]);$shop->home($request);ok($request->input('main_category')==='Pet Supplies','Existing subcategory links infer parent category');
 loginAs($seller);$reviewItem=DB::table('order_items')->where('order_id',$completedOrder??$oid)->first();DB::table('item_reviews')->insert(['order_item_id'=>$reviewItem->id,'buyer_id'=>$buyer,'seller_id'=>$seller,'stars'=>5,'comment'=>'A lovely local find','photos'=>'[]','created_at'=>now(),'updated_at'=>now()]);$html=(new App\Http\Controllers\MarketController())->panel(requestFor(),'ratings')->render();ok(str_contains($html,'A lovely local find')&&str_contains($html,'Reply to this review')&&!str_contains($html,'<details'),'Reviews render as named product cards with direct replies');
}
echo 'PASS: '.($n-$before)." workspace design and access checks.\n";
