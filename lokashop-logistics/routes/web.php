<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{CommonController as C,LogisticsController as L,LogisticsAdminController as A};
Route::get('/',[L::class,'landing']);Route::get('/track',[L::class,'track']);
Route::get('/login',[C::class,'authForm'])->name('login');Route::post('/login',[C::class,'login']);Route::get('/register',[C::class,'authForm']);Route::post('/register',[C::class,'register']);
Route::post('/logout',[C::class,'logout'])->middleware('auth');
Route::middleware(['auth','role:sorting_center|rider|logistics_admin'])->group(function(){Route::get('/dashboard',[C::class,'dashboard']);Route::get('/profile',[C::class,'profile']);Route::post('/profile',[C::class,'updateProfile']);Route::get('/messages',[C::class,'messages']);Route::post('/messages',[C::class,'sendMessage']);});
Route::middleware(['auth','role:sorting_center'])->prefix('center')->group(function(){Route::get('/areas',[L::class,'areas']);Route::post('/areas',[L::class,'area']);Route::get('/pickups',[L::class,'pickups']);Route::post('/pickups/{id}/verify',[L::class,'verifyPickup']);Route::get('/parcels',[L::class,'parcels']);Route::post('/parcels/{id}/receive',[L::class,'receive']);Route::post('/parcels/{id}/sort',[L::class,'sort']);Route::post('/parcels/{id}/assign',[L::class,'assign']);Route::post('/parcels/{id}/return',[L::class,'returned']);Route::get('/reports',[L::class,'report']);});
Route::middleware(['auth','role:rider'])->prefix('rider')->group(function(){Route::get('/assignments',[L::class,'assignments']);Route::post('/assignments/{id}/accept',[L::class,'accept']);Route::post('/parcels/{id}/status',[L::class,'riderStep']);Route::get('/earnings',[L::class,'earnings']);});

Route::middleware(['auth','role:logistics_admin'])->prefix('admin')->group(function(){
 Route::get('/approvals',[A::class,'approvals']);
 Route::get('/documents/{id}',[A::class,'document']);
 Route::post('/approvals/{id}',[A::class,'approve']);
 Route::get('/users',[A::class,'users']);
 Route::post('/users/{id}/toggle',[A::class,'toggle']);
});

Route::get('/register',[\App\Http\Controllers\CompanyRegistrationController::class,'form']);
Route::post('/register',[\App\Http\Controllers\CompanyRegistrationController::class,'register'])->middleware('throttle:5,1');
Route::get('/company/verify',[\App\Http\Controllers\CompanyRegistrationController::class,'verifyForm']);
Route::post('/company/verify',[\App\Http\Controllers\CompanyRegistrationController::class,'verify'])->middleware('throttle:10,1');
Route::post('/company/resend',[\App\Http\Controllers\CompanyRegistrationController::class,'resend'])->middleware('throttle:3,1');
Route::get('/activate/{id}/{token}',[\App\Http\Controllers\OperationsController::class,'activateForm']);
Route::post('/activate/{id}/{token}',[\App\Http\Controllers\OperationsController::class,'activate'])->middleware('throttle:5,1');
Route::get('/application/resubmit',[\App\Http\Controllers\ReviewController::class,'form']);
Route::post('/application/resubmit',[\App\Http\Controllers\ReviewController::class,'resubmit'])->middleware('throttle:5,1');
Route::middleware(['auth','role:sorting_center|rider|logistics_admin'])->get('/ops/{module}',[\App\Http\Controllers\OperationsController::class,'panel']);
Route::middleware(['auth','role:sorting_center'])->group(function(){
 foreach(['rider'=>'rider','rider/status'=>'riderStatus','rider/resend'=>'resend','rate'=>'rate','dispatch'=>'dispatch','remittance/confirm'=>'confirm','exception'=>'exception','override'=>'override','returned'=>'returned'] as $path=>$action)Route::post('/ops/'.$path,[\App\Http\Controllers\OperationsController::class,$action]);

});
Route::middleware(['auth','role:rider'])->group(function(){foreach(['availability'=>'availability','decline'=>'decline','remit'=>'remit','vehicle'=>'vehicle'] as $path=>$action)Route::post('/ops/'.$path,[\App\Http\Controllers\OperationsController::class,$action]);});
Route::middleware(['auth','role:logistics_admin'])->post('/admin/approvals/{id}',[\App\Http\Controllers\ReviewController::class,'approve']);

Route::middleware(['auth','role:sorting_center'])->get('/center/reports',[\App\Http\Controllers\ReportController::class,'index']);

Route::middleware(['auth','role:sorting_center|rider'])->post('/ops/location',[\App\Http\Controllers\OperationsController::class,'location']);
Route::middleware(['auth','role:logistics_admin'])->get('/ops/legacy',[\App\Http\Controllers\OperationsController::class,'panel'])->defaults('module','legacy');
Route::middleware(['auth','role:logistics_admin'])->post('/ops/legacy',[\App\Http\Controllers\OperationsController::class,'legacy']);

Route::middleware('auth')->get('/messages/{id}/attachment',function(int $id){$m=\Illuminate\Support\Facades\DB::table('messages')->where('id',$id)->where(fn($q)=>$q->where('sender_id',auth()->id())->orWhere('recipient_id',auth()->id()))->first();abort_unless($m&&$m->attachment,404);return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($m->attachment));});

Route::middleware(['auth','role:rider'])->post('/ops/remit/topup',[\App\Http\Controllers\OperationsController::class,'topup']);

Route::middleware(['auth','role:logistics_admin'])->post('/admin/users/{id}/status',[\App\Http\Controllers\AccountController::class,'status']);

Route::middleware(['auth','role:sorting_center'])->group(function(){
 Route::post('/center/transfers/{id}/offer',[\App\Http\Controllers\TransferController::class,'offer']);
 Route::post('/center/transfers/{id}/receive',[\App\Http\Controllers\TransferController::class,'receive']);
});
Route::middleware(['auth','role:rider'])->post('/rider/transfers/{id}/depart',[\App\Http\Controllers\TransferController::class,'depart']);

Route::middleware(['auth','role:sorting_center|rider|logistics_admin'])->post('/account/address',[\App\Http\Controllers\AccountController::class,'address']);

Route::middleware(['auth','role:logistics_admin'])->post('/admin/territory',[\App\Http\Controllers\AccountController::class,'territory']);

Route::post('/register/courier',[\App\Http\Controllers\PublicCourierController::class,'register'])->middleware('throttle:5,1');

// Uploaded images: restricted to image directories and safe raster formats.
Route::get('/media/{path}',function(string $path){
 abort_unless(preg_match('~^(uploads|stores|profiles|variants)/[A-Za-z0-9_./-]+\.(jpg|jpeg|png)$~i',$path)&&!str_contains($path,'..'),404);
 $disk=\Illuminate\Support\Facades\Storage::disk('public');$file=$disk->exists($path)?$disk->path($path):public_path('storage/'.$path);abort_unless(is_file($file),404);
 return response()->file($file,['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'public, max-age=3600']);
})->where('path','.*');



Route::get('/register/courier',[\App\Http\Controllers\PublicCourierController::class,'form']);

Route::middleware(['auth','role:rider'])->post('/rider/scan',[L::class,'scan']);
