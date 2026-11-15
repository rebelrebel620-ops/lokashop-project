<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{CommonController as C,ShopController as S,SellerController as V,AdminController as A};
Route::get('/',[S::class,'landing']);Route::get('/shop',[S::class,'home']);Route::get('/product/{id}',[S::class,'product']);
Route::get('/track/{code}',[C::class,'track']);
Route::get('/login',[C::class,'authForm'])->name('login');Route::post('/login',[C::class,'login'])->middleware('login.throttle');Route::get('/register',[C::class,'authForm']);Route::post('/register',[C::class,'register'])->middleware('throttle:5,1');
Route::post('/logout',[C::class,'logout'])->middleware('auth');
Route::middleware(['auth','role:buyer|seller|admin'])->group(function(){Route::get('/dashboard',[C::class,'dashboard']);Route::get('/profile',[C::class,'profile']);Route::post('/profile',[C::class,'updateProfile']);Route::get('/messages',[C::class,'messages']);Route::post('/messages',[C::class,'sendMessage']);});
Route::middleware(['auth','role:buyer'])->group(function(){Route::get('/cart',[S::class,'cart']);Route::post('/cart',[S::class,'add']);Route::post('/addresses',[S::class,'address']);Route::post('/checkout',[S::class,'checkout']);Route::get('/orders',[S::class,'orders']);Route::post('/orders/{id}/confirm',[S::class,'confirm']);Route::post('/orders/{id}/rating',[S::class,'rating']);});
Route::middleware(['auth','role:seller'])->prefix('seller')->group(function(){Route::get('/products',[V::class,'products']);Route::post('/products',[\App\Http\Controllers\MarketController::class,'product']);Route::post('/products/{id}',[\App\Http\Controllers\MarketController::class,'product']);Route::post('/products/{id}/variations',[V::class,'addVariation']);Route::get('/orders',[V::class,'orders']);Route::get('/orders/{id}/label',[V::class,'label']);Route::post('/orders/{id}/status',[V::class,'transition']);Route::post('/orders/{id}/pickup',[V::class,'pickup']);Route::post('/vouchers',[V::class,'voucher']);Route::get('/reports',[V::class,'report']);});
Route::middleware(['auth','role:admin'])->prefix('admin')->group(function(){Route::get('/approvals',[A::class,'approvals']);Route::get('/documents/{id}',[A::class,'document']);Route::post('/approvals/{id}',[A::class,'approve']);Route::get('/users',[A::class,'users']);Route::post('/users/{id}/toggle',[A::class,'toggle']);Route::get('/products',[A::class,'products']);Route::post('/products/{id}',[A::class,'productDecision']);Route::get('/reports',[A::class,'report']);Route::get('/announcements',[A::class,'announcements']);Route::post('/announcements',[A::class,'announcement']);Route::get('/complaints',[A::class,'complaints']);Route::post('/complaints/{id}',[A::class,'resolve']);});

Route::middleware(['auth','role:buyer'])->group(function(){Route::get('/complaints',fn()=>view('complaints',['links'=>app(C::class)->links(),'complaints'=>\Illuminate\Support\Facades\DB::table('complaints')->where('user_id',auth()->id())->get()]));Route::post('/complaints',function(\Illuminate\Http\Request $r){$v=$r->validate(['order_id'=>'required|integer','description'=>'required|string|max:3000']);abort_unless(\Illuminate\Support\Facades\DB::table('orders')->where('id',$v['order_id'])->where('buyer_id',auth()->id())->exists(),403);\Illuminate\Support\Facades\DB::table('complaints')->insert($v+['user_id'=>auth()->id(),'status'=>'open','created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Complaint sent.');});});

Route::get('/verify-email',[\App\Http\Controllers\VerificationController::class,'form']);
Route::post('/verify-email',[\App\Http\Controllers\VerificationController::class,'verify'])->middleware('throttle:10,1');
Route::post('/verify-email/resend',[\App\Http\Controllers\VerificationController::class,'resend'])->middleware('throttle:3,1');
Route::get('/terms',fn()=>view('terms'));

// Additional marketplace workflows.
Route::middleware(['auth','role:buyer|seller|admin'])->group(function(){
 Route::get('/tools/{module}',[\App\Http\Controllers\MarketController::class,'panel']);
 Route::post('/tools/password',[\App\Http\Controllers\AccountSecurityController::class,'password']);
 Route::post('/tools/contact/request',[\App\Http\Controllers\AccountSecurityController::class,'contactRequest']);
 Route::post('/tools/contact/verify',[\App\Http\Controllers\AccountSecurityController::class,'contactVerify']);
 Route::post('/tools/notifications/read',[\App\Http\Controllers\MarketController::class,'notificationsRead']);
});
Route::middleware(['auth','role:buyer'])->group(function(){
 Route::get('/addresses',fn()=>redirect('/tools/addresses'));
 foreach(['address'=>'address','address/delete'=>'addressDelete','wishlist'=>'wishlist','follow'=>'follow','claim'=>'claim','return'=>'returnCreate','return/escalate'=>'returnEscalate','review'=>'review'] as $path=>$action)Route::post('/tools/'.$path,[\App\Http\Controllers\MarketController::class,$action]);
 Route::post('/orders/{id}/cancel',[\App\Http\Controllers\MarketController::class,'cancel']);
 Route::post('/cart/{id}/update',function(\Illuminate\Http\Request $r,int $id){\App\Services\Cart::update($r,$id);return back()->with('success','Cart updated.');});
 Route::post('/cart/{id}/remove',function(int $id){\Illuminate\Support\Facades\DB::table('cart_items')->where('id',$id)->where('buyer_id',auth()->id())->delete();return back()->with('success','Item removed.');});
});
Route::middleware(['auth','role:seller'])->group(function(){
 foreach(['store'=>'store','category'=>'category','category/delete'=>'categoryDelete','product-categories'=>'productCategories','product'=>'product','product/action'=>'productAction','variant'=>'variant','variant/delete'=>'variantDelete','promotion'=>'promotion','promotion/end'=>'promotionEnd','voucher'=>'voucher','return/respond'=>'returnRespond','return/received'=>'returnReceived','review/reply'=>'reviewReply'] as $path=>$action)Route::post('/tools/'.$path,[\App\Http\Controllers\MarketController::class,$action]);
 Route::post('/seller/orders/{id}/cancel',[\App\Http\Controllers\MarketController::class,'cancel']);
 Route::post('/seller/products',[\App\Http\Controllers\MarketController::class,'product']);
 Route::post('/seller/products/{id}',[\App\Http\Controllers\MarketController::class,'product']);
 Route::post('/seller/products/{id}/variations',function(\Illuminate\Http\Request $r,int $id){$r->merge(['product_id'=>$id]);return app(\App\Http\Controllers\MarketController::class)->variant($r);});
});
Route::middleware(['auth','role:buyer|seller'])->get('/ratings',fn()=>redirect('/tools/ratings'));
Route::middleware(['auth','role:admin'])->group(function(){
 foreach(['payment'=>'payment','refund'=>'refund','payout/paid'=>'payoutPaid'] as $path=>$action)Route::post('/tools/'.$path,[\App\Http\Controllers\MarketController::class,$action]);
 Route::post('/admin/approvals/{id}',[\App\Http\Controllers\ReviewController::class,'approve']);
});
Route::get('/application/resubmit',[\App\Http\Controllers\ReviewController::class,'form']);
Route::post('/application/resubmit',[\App\Http\Controllers\ReviewController::class,'resubmit'])->middleware('throttle:5,1');
Route::middleware(['auth','role:buyer|seller'])->get('/orders/{id}/proof',[\App\Http\Controllers\DeliveryProofController::class,'show']);

Route::middleware(['auth','role:seller|admin'])->get('/seller/reports',[\App\Http\Controllers\ReportController::class,'index']);

Route::middleware(['auth','role:buyer'])->post('/addresses',[\App\Http\Controllers\MarketController::class,'address']);

Route::middleware(['auth','role:seller'])->post('/seller/vouchers',[\App\Http\Controllers\MarketController::class,'voucher']);

Route::middleware(['auth','role:seller'])->post('/tools/rts-received',[\App\Http\Controllers\MarketController::class,'rtsReceived']);

Route::middleware('auth')->get('/messages/{id}/attachment',function(int $id){$m=\Illuminate\Support\Facades\DB::table('messages')->where('id',$id)->where(fn($q)=>$q->where('sender_id',auth()->id())->orWhere('recipient_id',auth()->id()))->first();abort_unless($m&&$m->attachment,404);return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($m->attachment));});

Route::middleware(['auth','role:admin'])->post('/tools/platform-voucher',[\App\Http\Controllers\MarketController::class,'platformVoucher']);
Route::middleware(['auth','role:admin'])->post('/tools/dispute',[\App\Http\Controllers\MarketController::class,'disputeResolve']);
Route::middleware(['auth','role:seller'])->post('/tools/voucher/end',[\App\Http\Controllers\MarketController::class,'voucherEnd']);

Route::middleware(['auth','role:admin'])->get('/admin/reports',[\App\Http\Controllers\ReportController::class,'index']);

Route::middleware(['auth','role:seller'])->post('/tools/deal',[\App\Http\Controllers\MarketController::class,'deal']);
Route::middleware(['auth','role:buyer'])->post('/buy-now',[\App\Http\Controllers\MarketController::class,'buyNow']);

Route::middleware(['auth','role:buyer|seller|admin'])->get('/returns/{id}/photos/{index}',function(int $id,int $index){$x=\Illuminate\Support\Facades\DB::table('return_requests')->find($id);abort_unless($x&&(auth()->id()==$x->buyer_id||auth()->id()==$x->seller_id||auth()->user()->role==='admin'),403);$photos=json_decode($x->photos,true);abort_unless(isset($photos[$index]),404);return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($photos[$index]));});

Route::middleware('auth')->get('/application/drafts',[\App\Http\Controllers\DraftController::class,'form']);
Route::middleware('auth')->post('/application/drafts',[\App\Http\Controllers\DraftController::class,'save']);

Route::middleware(['auth','role:admin'])->post('/admin/users/{id}/status',[\App\Http\Controllers\AccountController::class,'status']);

Route::middleware(['auth','role:admin'])->post('/tools/platform-voucher/end',[\App\Http\Controllers\MarketController::class,'voucherEnd']);

Route::middleware(['auth','role:buyer|seller|admin'])->post('/account/address',[\App\Http\Controllers\AccountController::class,'address']);

Route::middleware('auth')->group(function(){Route::get('/appeal',[\App\Http\Controllers\AppealController::class,'form']);Route::post('/appeal',[\App\Http\Controllers\AppealController::class,'submit'])->middleware('throttle:5,1');});
Route::middleware(['auth','role:admin'])->group(function(){Route::get('/admin/appeals',[\App\Http\Controllers\AppealController::class,'index']);Route::post('/admin/appeals/{id}',[\App\Http\Controllers\AppealController::class,'resolve']);});

// Uploaded images: restricted to image directories and safe raster formats.
Route::get('/media/{path}',function(string $path){
 abort_unless(preg_match('~^(uploads|stores|profiles|variants)/[A-Za-z0-9_./-]+\.(jpg|jpeg|png)$~i',$path)&&!str_contains($path,'..'),404);
 $file=\App\Services\ProductImages::source($path);abort_unless($file,404);
 $size=request()->query('size');
 if($size!==null){abort_unless(is_string($size)&&in_array($size,['400','1200'],true),404);$file=\App\Services\ProductImages::optimized($file,(int)$size);}
 $response=response()->file($file,['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'public, max-age=3600']);
 $response->setLastModified((new \DateTimeImmutable())->setTimestamp(filemtime($file)));
 $response->isNotModified(request());
 return $response;
})->where('path','.*');





Route::middleware(['auth','role:admin'])->post('/admin/users/{id}/initial-category',function(\Illuminate\Http\Request $r,int $id){
 $v=$r->validate(['category'=>['required',\Illuminate\Validation\Rule::in(config('registration.categories'))]]);
 \Illuminate\Support\Facades\DB::transaction(function()use($id,$v){
 $store=\Illuminate\Support\Facades\DB::table('seller_businesses')->where('user_id',$id)->lockForUpdate()->first();abort_unless($store,404);
 abort_if(in_array($store->category,config('registration.categories'),true),422,'This store already has a permanent main category.');
 \Illuminate\Support\Facades\DB::table('seller_businesses')->where('user_id',$id)->update(['category'=>$v['category'],'updated_at'=>now()]);
 \App\Services\Support::audit('legacy-main-category','user',$id,['category'=>$store->category],$v,'Resolve legacy category');
 });return back()->with('success','Permanent main category assigned.');
});

Route::middleware(['auth','role:seller'])->get('/seller/address',fn()=>view('seller-address',['links'=>app(C::class)->links()]));

Route::middleware(['auth','role:seller'])->get('/seller/stock',[\App\Http\Controllers\SellerController::class,'stock']);

// Read-only user profiles are restricted to this website's administrator.
Route::middleware(['auth','role:admin'])->get('/admin/users/{id}',[\App\Http\Controllers\AdminUserDetailsController::class,'show'])->whereNumber('id');

Route::middleware(['auth','role:seller'])->post('/tools/deal/end',[\App\Http\Controllers\SellerPromotionController::class,'endDeal']);

Route::middleware(['auth','role:buyer|seller|admin'])->group(function(){
 Route::get('/wallet',[\App\Http\Controllers\WalletController::class,'index']);
 Route::post('/wallet/transfer',[\App\Http\Controllers\WalletController::class,'transfer'])->middleware('throttle:10,1');
});



Route::get('/live/state',[\App\Http\Controllers\LiveUpdatesController::class,'state'])->middleware('throttle:120,1');

Route::middleware(['auth','role:buyer|seller|admin'])->get('/returns/{id}/video',function(int $id){$x=\Illuminate\Support\Facades\DB::table('return_requests')->find($id);abort_unless($x&&(auth()->id()==$x->buyer_id||auth()->id()==$x->seller_id||auth()->user()->role==='admin'),403);abort_unless($x->video_path,404);return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($x->video_path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);});

// Serve product videos from the configured public disk, including Windows/shared storage.
Route::get('/product-videos/{id}',[\App\Http\Controllers\ProductVideoController::class,'show'])->whereNumber('id');

Route::get('/product/{id}/state',[\App\Http\Controllers\ProductStateController::class,'show'])->whereNumber('id');

Route::middleware(['auth','role:buyer|seller|admin'])->group(function(){Route::get('/notifications',[\App\Http\Controllers\NotificationController::class,'index']);Route::get('/notifications/count',[\App\Http\Controllers\NotificationController::class,'count']);Route::post('/notifications/read',[\App\Http\Controllers\NotificationController::class,'read']);});
