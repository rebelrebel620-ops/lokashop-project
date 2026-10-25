<?php
require __DIR__.'/admin_user_details_checks.php';
use Illuminate\Support\Facades\DB;
$before=$n;
if(!$logistics){
 loginAs($admin);$c=new App\Http\Controllers\AdminController;
 DB::table('categories')->where('id',$cat)->update(['main_category'=>'Electronics']);
 $pending=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Review Camera','description'=>'Camera for review','price'=>125,'stock'=>3,'approved'=>0,'active'=>1,'draft'=>0]);
 $rejected=DB::table('products')->insertGetId(['seller_id'=>$seller,'category_id'=>$cat,'name'=>'Rejected Camera','price'=>125,'stock'=>3,'approved'=>0,'active'=>0,'draft'=>0]);
 $view=$c->products(requestFor(['tab'=>'pending','q'=>'Review Camera','category'=>'Electronics','stock'=>'available']));
 ok($view->getData()['products']->total()===1,'Pending product search, category and stock filters combine');
 $html=$view->render();ok(str_contains($html,'Approved products')&&str_contains($html,'Approve product'),'Pending view shows tabs and decisions');
 $c->productDecision(requestFor(['decision'=>'approve']),$pending);
 $view=$c->products(requestFor(['tab'=>'approved','q'=>'Review Camera','visibility'=>'active']));ok($view->getData()['products']->total()===1,'Newly approved product moves to approved view');
 ok(!str_contains($view->render(),'Reject product'),'Approved listings have no review buttons');
 $pendingView=$c->products(requestFor(['tab'=>'pending']));ok(!$pendingView->getData()['products']->contains('id',$rejected),'Rejected products excluded from pending queue');
 try{$c->productDecision(requestFor(['decision'=>'reject']),$pending);throw new RuntimeException('Repeated approval allowed');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===422,'Stale review decisions refused');}
}
loginAs($logistics?$company:$admin);
$controller=new App\Http\Controllers\ReportController;
$r=requestFor(['q'=>'NOT_A_REAL_STATUS','format'=>'pdf']);$response=$controller->index($r);
ok($response->headers->get('Content-Type')==='application/pdf','PDF content type');ok(str_starts_with($response->getContent(),'%PDF-1.4'),'Actual PDF bytes returned');ok(str_contains($response->getContent(),'No records match'),'Search filters applied to PDF');
$rows=collect();for($i=1;$i<=90;$i++)$rows->push((object)['id'=>$i,'status'=>'DELIVERED','tracking_code'=>'TRACK-'.$i,'seller_id'=>2,'merchandise_total'=>1250.50,'store_discount'=>50,'commission'=>120.05,'shipping_fee'=>50,'total'=>1250.50,'created_at'=>'2026-10-25 12:00:00']);
$pdf=App\Services\ReportPdf::make(['id','seller_id','status','merchandise_total','store_discount','commission','shipping_fee','total','created_at'],$rows,['q'=>'TRACK']);file_put_contents('/tmp/lokashop-report-test.pdf',$pdf);
ok(substr_count($pdf,'/Type /Page ')>1,'Long reports paginate');ok(str_contains($pdf,'1,250.50'),'Last record retained');
echo 'PASS: '.($n-$before)." product/report checks.\n";
