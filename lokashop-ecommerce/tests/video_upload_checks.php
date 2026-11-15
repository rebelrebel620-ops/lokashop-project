<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\MarketController;
$before=$n;$controller=new MarketController();
loginAs($buyer);$data=['order_id'=>$oid,'reason'=>'Damaged','description'=>'The item arrived damaged','_request_key'=>(string)Illuminate\Support\Str::uuid()];
try{$controller->returnCreate(requestFor($data));throw new RuntimeException('Missing video accepted');}catch(ValidationException $e){ok(isset($e->errors()['video']),'Buyer return requires video proof');}
DB::table('orders')->where('id',$oid)->update(['status'=>'DELIVERED','delivered_at'=>now(),'refunded_at'=>null]);DB::table('parcels')->where('id',$pid)->update(['status'=>'DELIVERED']);DB::table('return_requests')->where('order_id',$oid)->delete();
$photo=tempnam(sys_get_temp_dir(),'proof');file_put_contents($photo,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jD9sAAAAASUVORK5CYII='));
$clip=__DIR__.'/fixtures/product-sample.mp4';$copy=tempnam(sys_get_temp_dir(),'video');copy($clip,$copy);
$controller->returnCreate(requestFor($data,['photos'=>[new UploadedFile($photo,'proof.png','image/png',null,true)],'video'=>new UploadedFile($copy,'proof.mp4','video/mp4',null,true)]));
$x=DB::table('return_requests')->where('order_id',$oid)->first();ok($x&&Storage::disk('local')->exists($x->video_path),'Valid return video stored privately');
loginAs($seller);$controller->returnRespond(requestFor(['id'=>$x->id,'decision'=>'approved','reason'=>'Video confirms product damage']));ok(DB::table('parcels')->find($pid)->return_request_id==$x->id,'Seller controller approval starts pickup');
try{$controller->returnReceived(requestFor(['id'=>$x->id]));throw new RuntimeException('Early receipt accepted');}catch(ValidationException $e){ok(isset($e->errors()['return']),'Seller cannot confirm receipt before reverse delivery');}
// Editing a product keeps photos and accepts/replaces/removes a public video.
DB::table('seller_businesses')->where('user_id',$seller)->update(['category'=>'Electronics']);DB::table('categories')->where('id',$cat)->update(['category_code'=>'TEST-VIDEO','main_category'=>'Electronics']);DB::table('products')->where('id',$p)->update(['images'=>'["uploads/existing.png"]']);
$fields=['name'=>'Test video product','description'=>'A product with a short demonstration video.','category_id'=>$cat,'price'=>100,'discount_percent'=>0,'stock'=>3,'weight'=>1];
$copy=tempnam(sys_get_temp_dir(),'video');copy($clip,$copy);$controller->product(requestFor($fields,['video'=>new UploadedFile($copy,'demo.mp4','video/mp4',null,true)]),$p);$product=DB::table('products')->find($p);ok($product->video_path&&Storage::disk('public')->exists($product->video_path),'Seller product video saved');ok(json_decode($product->images,true)===['uploads/existing.png'],'Video upload preserves existing photos');
$controller->product(requestFor($fields),$p);ok(DB::table('products')->find($p)->video_path===$product->video_path,'Editing without upload keeps product video');$controller->product(requestFor($fields+['remove_video'=>1]),$p);ok(DB::table('products')->find($p)->video_path===null,'Seller may remove video independently of photos');
@unlink($photo);echo 'PASS: '.($n-$before)." video upload and seller decision checks.\n";
$beforeView=$n;
$route=collect(app('router')->getRoutes()->getRoutes())->first(fn($route)=>$route->uri()==='returns/{id}/video');$serve=$route->getAction('uses');loginAs($buyer);ok($serve($x->id)->getFile()->isFile(),'Buyer can access own private return proof');loginAs($seller);ok($serve($x->id)->getFile()->isFile(),'Seller can access return proof');loginAs($other);try{$serve($x->id);throw new RuntimeException('Other buyer accessed evidence');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Other buyer cannot access private return video');}
loginAs($seller);requestFor();$html=(new App\Http\Controllers\SellerController())->products()->render();ok(str_contains($html,'name="video"'),'Seller product editor renders video upload');
DB::table('products')->where('id',$p)->update(['video_path'=>$product->video_path]);$gallery=view('product-gallery',['galleryProduct'=>DB::table('products')->find($p)])->render();ok(str_contains($gallery,'Product video'),'Compact product gallery includes video');echo 'PASS: '.($n-$beforeView)." evidence access and media view checks.\n";
loginAs($buyer);requestFor();$html=(new App\Http\Controllers\ShopController())->product($p)->render();ok(str_contains($html,'product-main-video')&&str_contains($html,'data-product-video'),'View Product renders integrated video gallery');echo "PASS: product detail video view rendered.\n";
