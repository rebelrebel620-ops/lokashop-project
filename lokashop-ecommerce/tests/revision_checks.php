<?php
// Extends the disposable regression setup with this revision's requested behavior.
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Auth,Hash};
use App\Services\{Commerce,Cart,Municipalities,Geography,Dispatch};
use App\Http\Controllers\{AccountController,TransferController};
use Illuminate\Validation\ValidationException;
$beforeNew=$n;
function invalid(callable $fn,string $field,string $label){try{$fn();throw new RuntimeException('Accepted invalid case: '.$label);}catch(ValidationException $e){ok(isset($e->errors()[$field]),$label);}}
loginAs($buyer);
invalid(fn()=>Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'manual','_request_key'=>(string)Illuminate\Support\Str::uuid()])),'payment_method','Online/manual payment rejected server-side');
invalid(fn()=>Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','store_vouchers'=>[$seller=>'EXAMPLE'],'_request_key'=>(string)Illuminate\Support\Str::uuid()])),'voucher','Multiple store-voucher input rejected');
Geography::validate(['region'=>'CALABARZON','province'=>'Laguna','city'=>'Santa Cruz','barangay'=>'Pagsawitan']);ok(true,'Bundled address hierarchy validates Santa Cruz');
invalid(fn()=>Geography::validate(['province'=>'Laguna','city'=>'Magdalena','barangay'=>'Pagsawitan']),'barangay','Barangay cannot be used with wrong municipality');
ok(Municipalities::key('Laguna','Sta. Cruz')===Municipalities::key('Laguna','Santa Cruz'),'Municipality aliases resolve to same territory');
$p1=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Same store alpha','price'=>200,'stock'=>20,'approved'=>1,'active'=>1,'weight'=>1]);
$p2=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Same store beta','price'=>100,'stock'=>20,'approved'=>1,'active'=>1,'weight'=>1]);
foreach([[$p1,1],[$p1,1],[$p2,1]] as [$product,$qty])Cart::add(requestFor(['product_id'=>$product,'quantity'=>$qty]));
$key=(string)Illuminate\Support\Str::uuid();$newOrders=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>$key]));
ok(count($newOrders)===2,'Different products from one store create two orders');
$first=DB::table('order_items')->whereIn('order_id',$newOrders)->where('product_id',$p1)->get();ok($first->count()===1&&$first[0]->quantity===2,'Two quantities of same product stay in one order');
ok(DB::table('orders')->whereIn('id',$newOrders)->sum('total')==605,'Store shipping charged once and allocated across product orders');
ok(DB::table('orders')->whereIn('id',$newOrders)->sum('commission')==50,'Product order commissions reconcile to 10 percent');
$rp=requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>$key]);ok(Commerce::checkout($rp)===$newOrders,'Split checkout replay creates no duplicate orders');
// Discount allocation for several product orders from a single store.
foreach([[$p1,1],[$p2,1]] as [$product,$qty])Cart::add(requestFor(['product_id'=>$product,'quantity'=>$qty]));
foreach([['NEWSTORE',$seller,'fixed',20],['NEWPLATFORM',null,'fixed',10],['NEWSHIP',null,'shipping',15]] as [$code,$owner,$type,$value])DB::table('vouchers')->insert(['code'=>$code,'seller_id'=>$owner,'type'=>$type,'value'=>$value,'discount_percent'=>0,'active'=>1,'claimable'=>1,'usage_limit'=>10,'per_buyer_limit'=>1]);
$discountOrders=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','voucher'=>'NEWSTORE','platform_voucher'=>'NEWPLATFORM','shipping_voucher'=>'NEWSHIP','_request_key'=>(string)Illuminate\Support\Str::uuid()]));
ok(DB::table('orders')->whereIn('id',$discountOrders)->sum('total')==340,'Three vouchers apply and reconcile across same-store product orders');
ok(DB::table('orders')->whereIn('id',$discountOrders)->sum('commission')==28,'Seller-funded discount reduces commission base');
ok(DB::table('vouchers')->where('code','NEWSTORE')->value('used_count')===1,'Store voucher redeemed once per checkout');
Commerce::cancel($discountOrders[0],'Testing split product cancellation',$buyer);ok(DB::table('vouchers')->where('code','NEWSTORE')->value('used_count')===1,'Cancelling one product retains shared voucher redemption');
Commerce::cancel($discountOrders[1],'Testing split product cancellation',$buyer);ok(DB::table('vouchers')->where('code','NEWSTORE')->value('used_count')===0,'Cancelling all product orders restores voucher once');
// Explicit status actions cannot be used across the two administrator scopes.
$adminId=user(config('app.site_kind')==='ecommerce'?'admin':'logistics_admin','statusadmin');loginAs($adminId);
$target=config('app.site_kind')==='ecommerce'?$other:$rider;$accounts=new AccountController();
foreach(['suspended','archived'] as $state){$accounts->status(requestFor(['status'=>$state,'reason'=>'Regression status reason']),$target);$targetUser=DB::table('users')->find($target);ok(!$targetUser->active&&$targetUser->account_status===$state,'Account '.$state.' blocks access');}
$accounts->status(requestFor(['status'=>'active','reason'=>'Regression restore reason']),$target);ok(DB::table('users')->find($target)->active==1,'Administrator can restore account');
$accounts->status(requestFor(['status'=>'banned','reason'=>'Permanent ban test']),$target);try{$accounts->status(requestFor(['status'=>'active','reason'=>'Cannot undo ban']),$target);throw new RuntimeException('Permanent ban restored');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===422,'Permanent ban cannot be restored');}
if(config('app.site_kind')==='logistics'){
 $destUser=user('sorting_center','destinationCompany');$destCenter=DB::table('sorting_centers')->insertGetId(['user_id'=>$destUser,'name'=>'Magdalena center','city'=>'Magdalena','province'=>'Laguna','municipality_key'=>Municipalities::key('Laguna','Magdalena')]);
 try{DB::table('sorting_centers')->insert(['user_id'=>user('sorting_center','duplicateCompany'),'name'=>'Duplicate center','municipality_key'=>Municipalities::key('Laguna','Magdalena')]);throw new RuntimeException('Duplicate municipality accepted');}catch(Illuminate\Database\UniqueConstraintViolationException $e){ok(true,'Database rejects second sorting center for municipality');}
 DB::table('addresses')->insert(['user_id'=>$destUser,'recipient'=>'Destination center','phone'=>'09170000000','street'=>'Center road','barangay'=>'Poblacion','city'=>'Magdalena','province'=>'Laguna','is_default'=>1]);
 $driver=user('rider','driver');DB::table('users')->where('id',$driver)->update(['company_id'=>$company,'courier_type'=>'driver','vehicle'=>'6-wheel truck','online'=>1,'last_seen_at'=>now()]);
 $transferOrder=$newOrders[0];$parcel=DB::table('parcels')->where('order_id',$transferOrder)->first();$parcelId=$parcel->id;
 DB::table('parcels')->where('id',$parcelId)->update(['center_id'=>$center,'status'=>'AT_SORTING_CENTER']);DB::table('orders')->where('id',$transferOrder)->update(['status'=>'AT_SORTING_CENTER','address_snapshot'=>json_encode(['recipient'=>'Private buyer','phone'=>'09171234567','street'=>'PRIVATE destination','barangay'=>'Poblacion','city'=>'Magdalena','province'=>'Laguna'])]);
 invalid(fn()=>Municipalities::courier(DB::table('users')->find($driver),$parcel,'pickup'),'rider_id','Driver cannot pick up from customer');
 invalid(fn()=>Municipalities::courier(DB::table('users')->find($rider),$parcel,'delivery'),'rider_id','Local rider cannot deliver outside registered municipality');
 DB::table('addresses')->insert(['user_id'=>$company,'recipient'=>'Company','phone'=>'09170000000','street'=>'Center street','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);loginAs($company);$transfer=new TransferController();invalid(fn()=>$transfer->offer(requestFor(['rider_id'=>$rider]),$parcelId),'rider_id','Local rider cannot be assigned center transfer');
 $transfer->offer(requestFor(['rider_id'=>$driver]),$parcelId);ok(DB::table('parcels')->find($parcelId)->destination_center_id===$destCenter,'Transfer selects center serving buyer municipality');
 loginAs($driver);$offer=DB::table('assignments')->where('parcel_id',$parcelId)->where('kind','transfer')->where('status','offered')->first();Dispatch::accept($offer->id,$driver);$transfer->depart($parcelId);ok(DB::table('parcels')->find($parcelId)->status==='IN_TRANSIT_CENTER','Accepted driver can depart source center');
 // A driver cannot confirm center receipt through the staff endpoint.
 try{$transfer->receive($parcelId);throw new RuntimeException('Driver received parcel at center');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Only destination center staff can confirm receipt');}
 loginAs($destUser);$transfer->receive($parcelId);$received=DB::table('parcels')->find($parcelId);ok($received->center_id===$destCenter&&$received->status==='AT_SORTING_CENTER','Destination receipt changes center and restores sorting stage');
 ok(DB::table('assignments')->where('id',$offer->id)->value('status')==='completed','Center receipt completes driver assignment once');
 loginAs($company);ok(strlen($transfer->index()->render())>100,'Transfer page renders');
 $legacy=user('rider','legacyCourier');$admin=user('logistics_admin','legacyAdmin');loginAs($admin);(new App\Http\Controllers\OperationsController())->legacy(requestFor(['rider_id'=>$legacy,'company_id'=>$company,'courier_type'=>'rider','vehicle'=>'motorcycle','reason'=>'Attach legacy rider for testing']));
 ok(DB::table('users')->find($legacy)->company_id===$company&&Municipalities::address($legacy)!==null,'Legacy rider linking sets company, type and service address');
}
// Real page rendering catches template and selector errors.
view()->share('errors',new Illuminate\Support\ViewErrorBag());
loginAs($buyer);if(config('app.site_kind')==='ecommerce'){
 $html=(new App\Http\Controllers\ShopController())->cart()->render();ok(substr_count($html,'name="platform_voucher"')===1&&substr_count($html,'name="shipping_voucher"')===1&&substr_count($html,'name="voucher"')===1,'Cart exposes exactly three voucher selectors');ok(!str_contains($html,'value="manual"'),'Cart offers COD only');ok(str_contains($html,'name="_request_key"'),'Cart contains checkout request key');
 $html=(new App\Http\Controllers\ShopController())->product($p1)->render();ok(str_contains($html,'My orders')&&str_contains($html,'/profile'),'Buyer product uses same application shell');
 loginAs($seller);ok(str_contains((new App\Http\Controllers\CommonController())->profile()->render(),'id="security"'),'Account page contains password/contact security');
 foreach(['store','vouchers'] as $module)ok(strlen((new App\Http\Controllers\MarketController())->panel(requestFor(),$module)->render())>100,'User friendly '.$module.' page renders');
 ok((new App\Http\Controllers\MarketController())->panel(requestFor(),'products')->getTargetUrl()===url('/seller/products'),'Legacy catalog redirects to Products');
 ok(strlen((new App\Http\Controllers\SellerController())->products()->render())>100,'Unified Products screen renders');
 loginAs($adminId);ok(str_contains((new App\Http\Controllers\AdminController())->users(requestFor())->render(),'name="status"'),'Admin status actions render');
 $router=$app['router'];$route=$router->getRoutes()->match(Illuminate\Http\Request::create('/admin/complaints','GET'));ok(str_contains($route->getActionName(),'AdminController@complaints'),'Admin complaints uses admin route');
 $html=(new App\Http\Controllers\AdminController())->complaints()->render();ok(strlen($html)>100,'Admin complaint screen renders');
}
echo 'PASS: '.($n-$beforeNew).' revision-specific checks; '.$n." checks including base workflow suite.\n";
