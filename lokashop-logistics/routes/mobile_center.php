<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MobileCenterController as Center;
// Every entry point authenticates and authorizes the bearer token in Center::user.
Route::prefix('center')->group(function () {
    Route::get('/export/{kind}',[Center::class,'export'])->middleware('throttle:15,1');
    Route::get('/documents/{id}',[Center::class,'document'])->whereNumber('id');
    Route::get('/messages',[Center::class,'messages']);
    Route::post('/messages',[Center::class,'sendMessage'])->middleware('throttle:30,1');
    Route::get('/messages/{id}/attachment',[Center::class,'attachment'])->whereNumber('id');
    Route::post('/address',[Center::class,'address']);
    Route::post('/action/{action}/{id?}',[Center::class,'mutate'])->whereNumber('id')->middleware('throttle:30,1');
    Route::get('/{module}',[Center::class,'index'])->middleware('throttle:120,1');
});
