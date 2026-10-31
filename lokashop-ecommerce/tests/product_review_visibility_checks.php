<?php
// Disposable SQLite integration test; does not use project MySQL.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Services\ProductReviews;
config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],'session.driver'=>'array']);DB::purge();
DB::unprepared('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT);CREATE TABLE order_items(id INTEGER PRIMARY KEY,product_id INTEGER,order_id INTEGER,quantity INTEGER);CREATE TABLE orders(id INTEGER PRIMARY KEY,status TEXT,refunded_at TEXT);CREATE TABLE item_reviews(id INTEGER PRIMARY KEY,order_item_id INTEGER,buyer_id INTEGER,stars INTEGER,comment TEXT,photos TEXT,seller_response TEXT,created_at TEXT);');
DB::table('users')->insert(['id'=>1,'name'=>'Example Buyer','email'=>'private@example.test']);
DB::table('order_items')->insert([['id'=>1,'product_id'=>10],['id'=>2,'product_id'=>10],['id'=>3,'product_id'=>20],['id'=>4,'product_id'=>10]]);
foreach([[1,1,5,'Great'],[2,2,3,'Okay'],[3,3,1,'Other product'],[4,4,9,'Invalid legacy stars']] as [$id,$item,$stars,$comment])DB::table('item_reviews')->insert(['id'=>$id,'order_item_id'=>$item,'buyer_id'=>1,'stars'=>$stars,'comment'=>$comment,'photos'=>'[]','seller_response'=>$id===1?'Thank you':null,'created_at'=>'2026-10-20 12:00:00']);
DB::table('orders')->insert([['id'=>1,'status'=>'DELIVERED','refunded_at'=>null],['id'=>2,'status'=>'COMPLETED','refunded_at'=>null],['id'=>3,'status'=>'PLACED','refunded_at'=>null],['id'=>4,'status'=>'COMPLETED','refunded_at'=>'2026-10-21 12:00:00'],['id'=>5,'status'=>'CANCELLED','refunded_at'=>null]]);
DB::table('order_items')->where('id',1)->update(['order_id'=>1,'quantity'=>3]);DB::table('order_items')->where('id',2)->update(['order_id'=>2,'quantity'=>2]);DB::table('order_items')->where('id',3)->update(['order_id'=>1,'quantity'=>8]);DB::table('order_items')->where('id',4)->update(['order_id'=>3,'quantity'=>9]);
DB::table('order_items')->insert([['id'=>5,'product_id'=>10,'order_id'=>4,'quantity'=>7],['id'=>6,'product_id'=>10,'order_id'=>5,'quantity'=>4]]);
$n=0;function check($ok,$message){global $n;if(!$ok)throw new RuntimeException($message);$n++;}
check(ProductReviews::sold(10)===5,'Sold sums units, excludes pending/cancelled/refunded and other products');
$s=ProductReviews::summary(10);check($s['count']===2,'Only the selected product and valid stars count');check($s['average']===4.0,'Average reflects actual ratings');check((int)$s['distribution']->get(5)===1,'Five-star distribution');check((int)$s['distribution']->get(3)===1,'Three-star distribution');
$list=ProductReviews::listing(10);check($list->total()===2,'Listing matches summary');check(!$list->getCollection()->contains('comment','Other product'),'Other product reviews excluded');check($list->getCollection()->contains('seller_response','Thank you'),'Seller response included');
$row=(array)$list->first();check(!array_key_exists('email',$row)&&!array_key_exists('buyer_id',$row),'Public payload omits account identifiers and email');
$empty=ProductReviews::summary(99);check($empty['count']===0&&$empty['average']===0.0,'Empty product has zero ratings without division errors');
echo "Passed $n review visibility checks.\n";
