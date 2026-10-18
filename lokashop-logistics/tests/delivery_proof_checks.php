<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Storage,Hash};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use App\Services\{Commerce,Cart,Workflow,Dispatch};
$before=$n;
// Prove an actual image was stored by the same Workflow used by couriers.
$completedOrder=$oid;$completedParcel=DB::table('parcels')->where('order_id',$completedOrder)->first();
ok($completedParcel->proof_path && Storage::disk('local')->exists($completedParcel->proof_path),'Delivery photo persists after source upload is removed');
if(config('app.site_kind')==='ecommerce') {
 $controller=new App\Http\Controllers\DeliveryProofController();
 loginAs($buyer);requestFor();$response=$controller->show($completedOrder);
 ok($response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse,'Owning buyer can open exact delivery image');
 ok(str_contains($response->headers->get('Cache-Control'),'no-store'),'Delivery image is private and not cached');
 loginAs($other);try{$controller->show($completedOrder);throw new RuntimeException('Wrong buyer saw proof');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===403,'Another buyer cannot open delivery proof');}
 loginAs($buyer);$html=(new App\Http\Controllers\ShopController())->orders()->render();ok(str_contains($html,'Proof of delivery for this order')&&str_contains($html,'/orders/'.$completedOrder.'/proof'),'Buyer orders render parcel-specific proof');
 $json=(new App\Http\Controllers\Api\BuyerApiController())->orderDetail($completedOrder)->getData();ok(str_ends_with($json->order->proof_url,'/api/orders/'.$completedOrder.'/proof'),'Buyer mobile API exposes protected proof endpoint');
 $proof=$completedParcel->proof_path;Storage::disk('local')->move($proof,$proof.'.backup');
 try{requestFor();$unavailable=$controller->show($completedOrder);ok($unavailable->getStatusCode()===404,'Missing shared image returns clear 404 rather than server error');}finally{Storage::disk('local')->move($proof.'.backup',$proof);}
} else {
 $controller=new App\Http\Controllers\LogisticsController();
 ok(App\Services\ParcelCode::parse('https://example.test/track?code=LKS-TEST')==='LKS-TEST','QR query links parse');
 ok(App\Services\ParcelCode::parse('https://example.test/track/LKS-TEST')==='LKS-TEST','Printed QR path links parse');
 ok(App\Services\ParcelCode::parse(' LKS-TEST ')==='LKS-TEST','Printed tracking code fallback parses');
 loginAs($rider);$request=requestFor(['code'=>'https://example.test/track/'.$completedParcel->tracking_code]);$response=$controller->scan($request);ok(str_contains($response->getTargetUrl(),'#assignment-'),'Web QR lookup finds owned assignment');
 $stranger=user('rider','qrstranger');loginAs($stranger);try{$controller->scan(requestFor(['code'=>$completedParcel->tracking_code]));throw new RuntimeException('Unassigned courier lookup allowed');}catch(ValidationException $ex){ok(isset($ex->errors()['code']),'Unassigned courier cannot scan another job');}
 loginAs($buyer);Cart::add(requestFor(['product_id'=>$p,'quantity'=>1]));$ids=Commerce::checkout(requestFor(['address_id'=>$address,'payment_method'=>'cod','_request_key'=>(string)Illuminate\Support\Str::uuid()]));$testOrder=$ids[0];$parcel=DB::table('parcels')->where('order_id',$testOrder)->first();
 loginAs($seller);Workflow::move($parcel->id,'CONFIRMED',$seller);Workflow::move($parcel->id,'PREPARING',$seller);DB::table('parcels')->where('id',$parcel->id)->update(['center_id'=>$center]);
 $assignment=DB::table('assignments')->insertGetId(['parcel_id'=>$parcel->id,'rider_id'=>$rider,'kind'=>'pickup','status'=>'offered','expires_at'=>now()->addMinute()]);Workflow::move($parcel->id,'READY_FOR_PICKUP',$seller);
 loginAs($rider);DB::table('users')->where('id',$rider)->update(['last_seen_at'=>now(),'online'=>1]);Dispatch::accept($assignment,$rider);
 try{$controller->riderStep(requestFor(['status'=>'PICKED_UP','scanned_code'=>'LKS-WRONG']),$parcel->id);throw new RuntimeException('Wrong QR accepted');}catch(ValidationException $ex){ok(isset($ex->errors()['scanned_code']),'Wrong parcel QR cannot confirm pickup');}
 $controller->riderStep(requestFor(['status'=>'PICKED_UP','scanned_code'=>$parcel->tracking_code]),$parcel->id);ok(DB::table('parcels')->find($parcel->id)->status==='PICKED_UP','Correct QR confirms pickup through web controller');
 loginAs($company);$controller->receive(requestFor(),$parcel->id);
 // Equivalent municipality names must be accepted by sorting.
 DB::table('delivery_areas')->where('id',$area)->update(['city'=>'City of Quezon City']);
 $controller->sort(requestFor(['area_id'=>$area]),$parcel->id);ok(DB::table('parcels')->find($parcel->id)->status==='SORTED','Sorting accepts equivalent municipality names');
 Dispatch::offer($parcel->id,$company,$rider);$delivery=DB::table('assignments')->where('parcel_id',$parcel->id)->where('kind','delivery')->first();loginAs($rider);Dispatch::accept($delivery->id,$rider);$controller->riderStep(requestFor(['status'=>'OUT_FOR_DELIVERY']),$parcel->id);
 DB::table('parcels')->where('id',$parcel->id)->update(['delivery_otp_hash'=>Hash::make('654321')]);$amount=DB::table('orders')->find($testOrder)->total;
 $upload=tempnam(sys_get_temp_dir(),'delivery-check');file_put_contents($upload,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD9sAAAAASUVORK5CYII='));
 $data=['status'=>'DELIVERED','scanned_code'=>$parcel->tracking_code,'otp'=>'654321','cod_amount'=>$amount];
 try{$controller->riderStep(requestFor($data),$parcel->id);throw new RuntimeException('Missing proof accepted');}catch(ValidationException $ex){ok(isset($ex->errors()['proof']),'Delivery requires proof image');}
 $request=requestFor($data,['proof'=>new UploadedFile($upload,'proof.png','image/png',null,true)]);$controller->riderStep($request,$parcel->id);$result=DB::table('parcels')->find($parcel->id);ok($result->status==='DELIVERED'&&Storage::disk('local')->exists($result->proof_path),'Web delivery stores photo and updates shared order');
 $html=$controller->assignments()->render();ok(str_contains($html,'Scan QR from photo')&&str_contains($html,$parcel->tracking_code),'Courier assignment screen renders scanner and real parcel codes');
 loginAs($buyer);Workflow::move($parcel->id,'COMPLETED',$buyer);ok(DB::table('orders')->find($testOrder)->status==='COMPLETED','Buyer can confirm courier delivery');@unlink($upload);
}
echo 'PASS: '.($n-$before)." delivery proof / QR integration checks.\n";
