<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Http\Request;use Illuminate\Support\Facades\Auth;
$logistics=str_contains(getcwd(),'logistics');$cases=$logistics?[
 ['track',fn()=> (new App\Http\Controllers\LogisticsController)->track(requestFor(['code'=>['bad']]))],
 ['login-email',fn()=>(new App\Http\Controllers\CommonController)->login(requestFor(['email'=>['bad'],'password'=>'bad']))],
 ['login-password',fn()=>(new App\Http\Controllers\CommonController)->login(requestFor(['email'=>'buyer@example.test','password'=>['bad']]))]
]:[
 ['seller-products',fn()=>(new App\Http\Controllers\SellerController)->products()],
 ['seller-orders',fn()=>(new App\Http\Controllers\SellerController)->orders()],
 ['api-login-email',fn()=>(new App\Http\Controllers\Api\BuyerApiController)->login(requestFor(['email'=>['bad'],'password'=>'bad']))],
 ['api-login-password',fn()=>(new App\Http\Controllers\Api\BuyerApiController)->login(requestFor(['email'=>'buyer@example.test','password'=>['bad']]))],
 ['catalog',fn()=>(new App\Http\Controllers\ShopController)->home(requestFor(['category'=>['bad']]))],
 ['login-email',fn()=>(new App\Http\Controllers\CommonController)->login(requestFor(['email'=>['bad'],'password'=>'bad']))],
 ['login-password',fn()=>(new App\Http\Controllers\CommonController)->login(requestFor(['email'=>'buyer@example.test','password'=>['bad']]))]
];$start=$n;
foreach($cases as [$label,$fn]){loginAs($seller);requestFor(['tab'=>['bad'],'q'=>['bad']]);try{$fn();throw new RuntimeException('FAIL: '.$label.' accepted malformed input');}catch(Illuminate\Validation\ValidationException $e){ok(true,$label.' rejects malformed input before processing');}catch(Throwable $e){throw $e;}}
echo 'PASS: '.($n-$start)." malformed web input checks.\n";
