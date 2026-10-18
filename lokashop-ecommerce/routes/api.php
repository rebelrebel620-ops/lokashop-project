<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BuyerApiController as B;

// Public
Route::post('/register', [B::class, 'register'])->middleware('throttle:5,1');
Route::post('/registration/verify', [\App\Http\Controllers\Api\BuyerVerificationController::class, 'verify'])->middleware('throttle:10,1');
Route::post('/registration/resend', [\App\Http\Controllers\Api\BuyerVerificationController::class, 'resend'])->middleware('throttle:5,1');
Route::post('/login', [B::class, 'login'])->middleware('throttle:10,1');
Route::get('/categories', [B::class, 'categories']);
Route::get('/products', [B::class, 'products']);
Route::get('/products/{id}', [B::class, 'product']);

// Authenticated buyer
Route::middleware('api.buyer')->group(function () {
    Route::post('/logout', [B::class, 'logout']);
    Route::get('/me', [B::class, 'me']);
    Route::post('/profile', [B::class, 'updateProfile']);

    Route::get('/cart', [B::class, 'cart']);
    Route::post('/cart', [B::class, 'cartAdd']);
    Route::post('/cart/{id}', [B::class, 'cartUpdate']);
    Route::delete('/cart/{id}', [B::class, 'cartRemove']);

    Route::get('/addresses', [B::class, 'addresses']);
    Route::post('/addresses', [B::class, 'addressAdd']);
    Route::post('/addresses/{id}/default', [B::class, 'addressSetDefault']);

    Route::post('/checkout', [B::class, 'checkout']);
    Route::get('/orders', [B::class, 'orders']);
    Route::get('/orders/{id}', [B::class, 'orderDetail']);
    Route::post('/orders/{id}/confirm', [B::class, 'orderConfirm']);
    Route::post('/orders/{id}/rating', [B::class, 'orderRating']);

    Route::get('/vouchers', [B::class, 'vouchers']);
    Route::get('/notifications', [B::class, 'notifications']);

    Route::get('/messages/contacts', [B::class, 'messageContacts']);
    Route::get('/messages/{userId}', [B::class, 'messageThread']);
    Route::post('/messages', [B::class, 'sendMessage']);

    Route::get('/complaints', [B::class, 'complaints']);
    Route::post('/complaints', [B::class, 'complaintCreate']);
});

Route::middleware('api.buyer')->group(function(){
 Route::post('/addresses',[\App\Http\Controllers\MarketController::class,'address']);
 Route::post('/orders/{id}/cancel',[\App\Http\Controllers\MarketController::class,'cancel']);
 foreach(['wishlist'=>'wishlist','follow'=>'follow','vouchers/claim'=>'claim','returns'=>'returnCreate','returns/escalate'=>'returnEscalate','reviews'=>'review','notifications/read'=>'notificationsRead'] as $path=>$action)Route::post('/'.$path,[\App\Http\Controllers\MarketController::class,$action]);
 Route::get('/wishlist',fn()=>response()->json(\Illuminate\Support\Facades\DB::table('wishlists')->where('buyer_id',auth()->id())->get()));
 Route::get('/returns',fn()=>response()->json(\Illuminate\Support\Facades\DB::table('return_requests')->where('buyer_id',auth()->id())->get()));
});

Route::middleware('api.buyer')->group(function(){Route::get('/appeal',[\App\Http\Controllers\AppealController::class,'form']);Route::post('/appeal',[\App\Http\Controllers\AppealController::class,'submit'])->middleware('throttle:5,1');});

Route::middleware('api.buyer')->get('/orders/{id}/proof',[\App\Http\Controllers\DeliveryProofController::class,'show']);

// Native buyer parity with the web workspace.
Route::get('/catalog/categories',[\App\Http\Controllers\Api\MobileBuyerController::class,'categories']);
Route::middleware('api.buyer')->group(function(){
 $m=\App\Http\Controllers\Api\MobileBuyerController::class;
 Route::post('/checkout/quote',[$m,'quote']);
 Route::get('/wishlist',[$m,'wishlist']);Route::get('/following',[$m,'stores']);
 Route::get('/stores/{id}',[$m,'store'])->whereNumber('id');
 Route::get('/vouchers',[$m,'vouchers']);Route::get('/reviews',[$m,'reviews']);Route::get('/returns',[$m,'returns']);
 Route::get('/returns/{id}/photos/{index}',[$m,'returnPhoto'])->whereNumber(['id','index']);
 Route::get('/messages/{id}/attachment',[$m,'attachment'])->whereNumber('id');
 Route::post('/addresses/delete',[\App\Http\Controllers\MarketController::class,'addressDelete']);
 Route::post('/account/password',[$m,'password'])->middleware('throttle:5,1');
 Route::post('/account/contact/request',[$m,'contactRequest'])->middleware('throttle:3,1');
 Route::post('/account/contact/verify',[$m,'contactVerify'])->middleware('throttle:10,1');
});

Route::get('/public/stores/{id}',[\App\Http\Controllers\Api\MobileBuyerController::class,'store'])->whereNumber('id');
