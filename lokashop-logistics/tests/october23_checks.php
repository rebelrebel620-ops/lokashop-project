<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Mail};
use App\Services\{Cart,Commerce,PickupRequests,Workflow,Dispatch,ShippingEarnings,Municipalities};
Mail::fake();$before=$n;
// Five units are one order and one placed notification; stock decreases exactly once.
$product=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Five unit test product','price'=>100,'stock'=>12,'approved'=>1,'active'=>1,'weight'=>1]);
DB::table('cart_items')->where('buyer_id',$buyer)->delete();
loginAs($buyer);Cart::add(requestFor(['product_id'=>$product,'quantity'=>5]));
// No active destination rate: use unchanged PHP 50 standard shipping.
DB::table('shipping_rates')->update(['active'=>0]);
$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));
ok(count($ids)===1,'Five identical items create one order');$testOrder=$ids[0];
ok(DB::table('products')->find($product)->stock===7,'Five ordered units deducted immediately');
ok(DB::table('notifications')->where('user_id',$buyer)->where('body',"Order #{$testOrder} placed.")->count()===1,'One order placed notification for five items');
$order=DB::table('orders')->find($testOrder);
ok((float)$order->shipping_fee===50.0,'Standard shipping stays PHP 50');
ok((float)$order->shipping_commission===10.0,'Logistics gets 20 percent of PHP 50');
ok(ShippingEarnings::amount($testOrder,'pickup')===0.0,'Seller pickup costs and earns zero');
ok(ShippingEarnings::amount($testOrder,'delivery')===40.0,'Delivery rider gets PHP 40');
$parcel=DB::table('parcels')->where('order_id',$testOrder)->first();
loginAs($seller);Workflow::move($parcel->id,'CONFIRMED',$seller);Workflow::move($parcel->id,'PREPARING',$seller);
PickupRequests::request($testOrder,$seller,999999); // Seller cannot force a rider ID, including one supplied by an older client.
$assignment=DB::table('assignments')->where('parcel_id',$parcel->id)->first();
ok($assignment->rider_id===null,'Seller request contains no selected rider');
ok(DB::table('parcels')->find($parcel->id)->center_id===$center,'Request reaches seller municipality center');
if(config('app.site_kind')==='logistics'){
 $controller=new App\Http\Controllers\LogisticsController();
 loginAs($company);$html=$controller->pickups()->render();ok(str_contains($html,'Select area rider'),'Center sees area rider selector');
 $controller->verifyPickup(requestFor(['rider_id'=>$rider]),$assignment->id);
 ok(DB::table('parcels')->find($parcel->id)->status==='READY_FOR_PICKUP','Center approval removes Preparing blockage');
 ok(DB::table('orders')->find($testOrder)->status==='READY_FOR_PICKUP','Buyer and seller order state stays synchronized');
 DB::table('users')->where('id',$rider)->update(['online'=>1,'last_seen_at'=>now()]);
 loginAs($rider);Dispatch::accept($assignment->id,$rider);
 $controller->riderStep(requestFor(['status'=>'PICKED_UP','scanned_code'=>$parcel->tracking_code]),$parcel->id);
 ok(DB::table('orders')->find($testOrder)->status==='PICKED_UP','Correct scan and confirmation advance buyer order');
 ok((float)DB::table('assignments')->find($assignment->id)->earnings===0.0,'Pickup completion releases no earnings');
 loginAs($company);$controller->receive(requestFor(),$parcel->id);
 ok(DB::table('parcels')->find($parcel->id)->status==='AT_SORTING_CENTER','Center receives scanned pickup');
 $areaId=DB::table('delivery_areas')->where('center_id',$center)->where('barangay','Commonwealth')->value('id');
 $controller->sort(requestFor(['area_id'=>$areaId]),$parcel->id);
 ok(DB::table('parcels')->find($parcel->id)->status==='SORTED','Sort action becomes available and advances parcel');
 Dispatch::offer($parcel->id,$company,$rider);
 $delivery=DB::table('assignments')->where('parcel_id',$parcel->id)->where('kind','delivery')->first();
 ok((float)$delivery->earnings===40.0,'Delivery offer quotes 80 percent only once');
 // Area validation rejects another barangay at sorting.
 DB::table('parcels')->where('id',$parcel->id)->update(['status'=>'AT_SORTING_CENTER']);
 $wrong=DB::table('delivery_areas')->insertGetId(['center_id'=>$center,'name'=>'Wrong barangay','barangay'=>'Other barangay','city'=>'Quezon City','province'=>'Metro Manila','rider_id'=>$rider]);
 $controller->sort(requestFor(['area_id'=>$wrong]),$parcel->id);ok(DB::table('parcels')->find($parcel->id)->delivery_area_id!==$wrong,'Manual area input is ignored; buyer barangay matches automatically');
 // Approval authority: logistics admin cannot accept riders; only their own local center can.
 $pending=user('rider','pendinglocaloct23');DB::table('users')->where('id',$pending)->update(['approval_status'=>'pending','company_id'=>$company]);
 DB::table('addresses')->insert(['user_id'=>$pending,'recipient'=>'Pending','phone'=>'09171111111','street'=>'Test','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);
 $adminId=user('logistics_admin','approveroct23');loginAs($adminId);$review=new App\Http\Controllers\ReviewController();
 try{$review->approve(requestFor(['decision'=>'approved']),$pending);throw new RuntimeException('Admin approved rider');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===422,'Logistics admin cannot approve riders');}
 $otherCenter=user('sorting_center','othercenteroct23');loginAs($otherCenter);
 try{$review->approve(requestFor(['decision'=>'approved']),$pending);throw new RuntimeException('Foreign center approved rider');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Other center cannot approve rider');}
 loginAs($company);$review->approve(requestFor(['decision'=>'approved']),$pending);ok(DB::table('users')->find($pending)->approval_status==='approved','Own municipality center approves rider');
 $html=(new App\Http\Controllers\LogisticsAdminController())->approvals()->render();ok(str_contains($html,'Review rider applications'),'Center approval page renders');
 loginAs($adminId);$html=(new App\Http\Controllers\LogisticsAdminController())->commissions()->render();ok(str_contains($html,'Shipping commission'),'Admin commission report renders');
}else{
 loginAs($buyer);DB::table('users')->where('id',$buyer)->update(['profile_photo'=>'profiles/test.png']);loginAs($buyer);
 Cart::add(requestFor(['product_id'=>$product,'quantity'=>5]));
 $html=(new App\Http\Controllers\ShopController())->cart()->render();
 ok(str_contains($html,'data-quantity-step="-1"')&&str_contains($html,'data-quantity-step="1"'),'Cart renders minus and plus buttons');
 ok(str_contains($html,'/media/profiles/test.png'),'Buyer header displays profile image');
 ok(str_contains($html,'title="Cart">&#128722;<span class="badge-dot">1</span>'),'Cart badge counts one product instead of five units');
 $html=(new App\Http\Controllers\ShopController())->product($product)->render();
 ok(str_contains($html,'<strong>Store:</strong> Test store')&&str_contains($html,'<strong>Seller:</strong> seller'),'Product shows store and seller names');
 loginAs($seller);$html=(new App\Http\Controllers\SellerController())->orders()->render();ok(!str_contains($html,'<select name="rider_id">'),'Seller has no rider selection');
 $json=(new App\Http\Controllers\SellerController())->stock()->getData(true);ok(collect($json)->firstWhere('id',$product)['stock']===7,'Seller stock endpoint reports deducted stock');
}
echo 'PASS: '.($n-$before)." October 23 integration checks.\n";
