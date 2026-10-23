<?php
require __DIR__.'/mobile_api_checks.php';
use Illuminate\Support\Facades\{DB,Mail,Cache};
use Illuminate\Validation\ValidationException;
use App\Services\{Commerce,Cart};
use App\Http\Controllers\Api\MobileBuyerController;
$before=$n;$controller=new MobileBuyerController();loginAs($buyer);
DB::table('promotions')->insert(['seller_id'=>$seller,'product_id'=>$p,'type'=>'flash','price'=>60,'quantity'=>1,'used'=>0,'active'=>1,'starts_at'=>now()->subHour(),'ends_at'=>now()->addHour()]);
$vid=DB::table('vouchers')->insertGetId(['seller_id'=>$seller,'code'=>'MOBILE10','discount_percent'=>0,'type'=>'fixed','value'=>10,'active'=>1,'claimable'=>1,'per_buyer_limit'=>2,'usage_limit'=>10]);
Cart::add(requestFor(['product_id'=>$p,'quantity'=>2]));$request=['address_id'=>$address,'payment_method'=>'cod','voucher'=>'MOBILE10'];$quote=Commerce::checkout(requestFor($request),true);
ok($quote['total']==215,'Quote allocates limited flash stock, regular unit, shipping and voucher');ok(DB::table('promotions')->where('product_id',$p)->where('type','flash')->value('used')===0,'Quote does not consume flash stock');ok(DB::table('voucher_claims')->where('voucher_id',$vid)->count()===0,'Quote does not claim voucher');ok(DB::table('voucher_redemptions')->where('voucher_id',$vid)->count()===0,'Quote does not redeem voucher');$placed=Commerce::checkout(requestFor($request+['_request_key'=>(string)Illuminate\Support\Str::uuid()]));ok(DB::table('orders')->whereIn('id',$placed)->sum('total')==$quote['total'],'Real flash/voucher checkout matches side-effect-free quote');
config(['cache.default'=>'array','cache.stores.array'=>['driver'=>'array']]);Cache::purge();
$mailer=new class {public array $codes=[];public function raw($text,$callback){preg_match('/code is ([0-9]{6})/',$text,$m);$this->codes[]=$m[1]??'';$callback(new class {public function to($email){return $this;}public function subject($subject){return $this;}});}};Mail::swap($mailer);
$challenge=$controller->contactRequest(requestFor(['password'=>'Updated@1234','email'=>'changedbuyer@example.test']))->getData(true)['challenge'];ok(strlen($challenge)===40&&count($mailer->codes)===1,'Native contact request sends one email and returns only a challenge');
try{$controller->contactVerify(requestFor(['challenge'=>$challenge,'otp'=>'000000']));throw new RuntimeException('Wrong OTP accepted');}catch(ValidationException $e){ok(isset($e->errors()['otp']),'Wrong native contact OTP is rejected');}
$cache=Cache::get('buyer-contact:'.$buyer.':'.$challenge);ok($cache['attempts']===1,'Incorrect contact OTP attempt persists');
$controller->contactVerify(requestFor(['challenge'=>$challenge,'otp'=>$mailer->codes[0]]));$updated=DB::table('users')->find($buyer);ok($updated->email==='changedbuyer@example.test'&&$updated->remember_token===null,'Correct native OTP updates contact and revokes token');ok(Cache::get('buyer-contact:'.$buyer.':'.$challenge)===null,'Verified contact challenge cannot be replayed');
loginAs($buyer);$challenge2=$controller->contactRequest(requestFor(['password'=>'Updated@1234','phone'=>'09171239999']))->getData(true)['challenge'];Illuminate\Support\Carbon::setTestNow(now()->addMinutes(6));try{$controller->contactVerify(requestFor(['challenge'=>$challenge2,'otp'=>end($mailer->codes)]));throw new RuntimeException('Expired challenge accepted');}catch(ValidationException $e){ok(isset($e->errors()['otp']),'Native contact OTP expires at original five-minute deadline');}
echo 'PASS: '.($n-$before)." native security / flash quote checks.\n";
