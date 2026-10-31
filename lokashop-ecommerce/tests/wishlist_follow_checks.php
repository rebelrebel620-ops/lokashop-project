<?php
// Disposable in-memory integration checks; never uses the project's MySQL.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Auth};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\WishlistController;
use App\Models\User;
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],'session.driver'=>'array']);DB::purge();
DB::unprepared('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,role TEXT,active INTEGER,account_status TEXT,approval_status TEXT);CREATE TABLE seller_businesses(id INTEGER PRIMARY KEY,user_id INTEGER,name TEXT);CREATE TABLE store_follows(id INTEGER PRIMARY KEY,buyer_id INTEGER,seller_id INTEGER,created_at TEXT,updated_at TEXT,UNIQUE(buyer_id,seller_id));CREATE TABLE wishlists(id INTEGER PRIMARY KEY,buyer_id INTEGER,product_id INTEGER,created_at TEXT,updated_at TEXT,UNIQUE(buyer_id,product_id));CREATE TABLE categories(id INTEGER PRIMARY KEY,name TEXT);CREATE TABLE products(id INTEGER PRIMARY KEY,seller_id INTEGER,category_id INTEGER,name TEXT,active INTEGER,approved INTEGER,draft INTEGER,deleted_at TEXT);');
DB::table('users')->insert([['id'=>1,'name'=>'Buyer','role'=>'buyer','active'=>1,'account_status'=>'active','approval_status'=>'approved'],['id'=>2,'name'=>'Other buyer','role'=>'buyer','active'=>1,'account_status'=>'active','approval_status'=>'approved'],['id'=>3,'name'=>'Seller','role'=>'seller','active'=>1,'account_status'=>'active','approval_status'=>'approved']]);
DB::table('seller_businesses')->insert(['user_id'=>3,'name'=>'Example store']);DB::table('categories')->insert(['id'=>1,'name'=>'Example']);DB::table('products')->insert(['id'=>1,'seller_id'=>3,'category_id'=>1,'name'=>'Example product','active'=>1,'approved'=>1,'draft'=>0]);Auth::setUser(User::find(1));
function callSave(array $data,bool $store=true){return WishlistController::save(Request::create('/test','POST',$data,[],[],['HTTP_ACCEPT'=>'application/json']),$store);}
$n=0;function check($ok,$message){global $n;if(!$ok)throw new RuntimeException($message);$n++;}
callSave(['seller_id'=>3,'enabled'=>1]);callSave(['seller_id'=>3,'enabled'=>1]);check(DB::table('store_follows')->where('buyer_id',1)->count()===1,'Repeated follows must not duplicate');
DB::table('store_follows')->insert(['buyer_id'=>2,'seller_id'=>3]);callSave(['seller_id'=>3,'enabled'=>0]);check(!DB::table('store_follows')->where('buyer_id',1)->exists(),'Unfollow removes own relationship');check(DB::table('store_follows')->where('buyer_id',2)->exists(),'Other buyer follow preserved');
try{callSave(['seller_id'=>3]);throw new RuntimeException('Missing enabled must fail');}catch(ValidationException $e){check(isset($e->errors()['enabled']),'Missing intent rejected');}
callSave(['product_id'=>1,'enabled'=>1],false);callSave(['product_id'=>1,'enabled'=>1],false);check(DB::table('wishlists')->count()===1,'Repeated saves do not duplicate');
DB::table('products')->where('id',1)->update(['active'=>0]);callSave(['product_id'=>1,'enabled'=>0],false);check(DB::table('wishlists')->count()===0,'Unavailable item can still be removed');
try{callSave(['product_id'=>1,'enabled'=>1],false);throw new RuntimeException('Unavailable product must fail');}catch(ValidationException $e){check(isset($e->errors()['product_id']),'Unavailable product cannot be newly saved');}
DB::table('users')->where('id',3)->update(['active'=>0]);try{callSave(['seller_id'=>3,'enabled'=>1]);throw new RuntimeException('Unavailable seller must fail');}catch(ValidationException $e){check(isset($e->errors()['seller_id']),'Unavailable seller cannot be newly followed');}
echo "Passed $n wishlist/follow checks.\n";
