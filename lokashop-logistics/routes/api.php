<?php
use App\Http\Controllers\RiderApiController as R;
use Illuminate\Support\Facades\Route;

// All routes here are served under /api (see bootstrap/app.php).
Route::get('/ping', fn () => response()->json(['ok' => true, 'app' => 'lokashop-logistics']));

Route::prefix('rider')->group(function () {
    Route::post('/login', [R::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('rider.api')->group(function () {
        Route::get('/me', [R::class, 'me']);
        Route::post('/profile', [R::class, 'updateProfile']);
        Route::get('/assignments', [R::class, 'assignments']);
        Route::post('/assignments/{id}/accept', [R::class, 'accept'])->whereNumber('id');
        Route::post('/parcels/{id}/status', [R::class, 'status'])->whereNumber('id');
        Route::post('/scan', [R::class, 'scan']);
        Route::get('/earnings', [R::class, 'earnings']);
    });
});

Route::middleware('rider.api')->prefix('rider')->group(function(){
 foreach(['availability'=>'availability','decline'=>'decline','remit'=>'remit','vehicle'=>'vehicle'] as $path=>$action)Route::post('/'.$path,[\App\Http\Controllers\OperationsController::class,$action]);
 Route::post('/logout',function(){\Illuminate\Support\Facades\DB::table('users')->where('id',auth()->id())->update(['online'=>0,'remember_token'=>null,'last_seen_at'=>now()->subMinutes(31)]);return response()->json(['message'=>'Logged out.']);});
});

Route::middleware('rider.api')->post('/rider/location',[\App\Http\Controllers\OperationsController::class,'location']);

Route::middleware('rider.api')->post('/rider/remit/topup',[\App\Http\Controllers\OperationsController::class,'topup']);

Route::middleware('rider.api')->post('/rider/transfers/{id}/depart',[\App\Http\Controllers\TransferController::class,'depart']);
