<?php
require __DIR__.'/wallet_earnings_checks.php';
use Illuminate\Support\Facades\DB;
use App\Services\Wallet;
use App\Http\Controllers\{CourierFinancialController,LogisticsController,CommonController};
$begin=$n;$fd=user('rider','financereportdriver');$fr=user('rider','financereportrider');DB::table('users')->where('id',$fd)->update(['courier_type'=>'driver','company_id'=>$company]);DB::table('users')->where('id',$fr)->update(['courier_type'=>'rider','company_id'=>$company]);
$fo=walletOrder('COMPLETED');DB::table('parcels')->where('order_id',$fo)->update(['tracking_code'=>'FINANCE-TRACK-123']);$fp=DB::table('parcels')->where('order_id',$fo)->value('id');
function financeJob(int $courier,int $parcel,float $amount,string $payment,string $status='completed',string $kind='transfer'):int{global $company; $id=DB::table('assignments')->insertGetId(['rider_id'=>$courier,'parcel_id'=>$parcel,'kind'=>$kind,'status'=>$status,'earnings'=>$amount,'created_at'=>now(),'updated_at'=>now()]);if($payment!=='none')DB::table('wallet_courier_payments')->insert(['assignment_id'=>$id,'center_user_id'=>$company,'courier_user_id'=>$courier,'amount_cents'=>(int)round($amount*100),'status'=>$payment,'paid_at'=>$payment==='paid'?now():null,'created_at'=>now(),'updated_at'=>now()]);return $id;}
financeJob($fd,$fp,100,'paid');financeJob($fd,$fp,50,'pending');financeJob($fd,$fp,25,'none');financeJob($fd,$fp,999,'none','accepted');financeJob($fd,$fp,0,'none','completed','pickup');financeJob($fr,$fp,40,'paid','completed','delivery');
Wallet::creditEarnings($fd,10000,'finance-report-paid-job','courier_earnings','Report fixture earned');Wallet::cashIn($fd,50000,'finance-report-cash-in-01');
loginAs($fd);$before=Wallet::balance($fd);$controller=new CourierFinancialController;$report=(new LogisticsController)->earnings(requestFor());$data=$report->getData();
ok($report->name()==='courier-financial'&&$data['isDriver']===true,'Driver Earnings opens a financial report');
ok((int)$data['stats']->jobs===4,'Only owned completed assignments appear');
ok((float)$data['stats']->earned===175.0,'Earned total excludes incomplete jobs and cash-ins');
ok((float)$data['stats']->paid===100.0&&(float)$data['stats']->unpaid===75.0,'Paid, pending and unqueued earnings reconcile');
ok($data['rows']->perPage()===12,'Financial report paginates at 12');
$html=$report->render();ok(str_contains($html,'Financial report')&&str_contains($html,'Open Wallet'),'Report and wallet remain separate');
ok(str_contains($html,'Not yet queued')&&str_contains($html,'Pending center payment')&&str_contains($html,'No fee'),'Report distinguishes legacy unpaid, pending and free pickup jobs');
ok(!str_contains($html,'Add demo funds')&&!str_contains($html,'Send demo money'),'Financial report has no funding or transfer forms');
$d=$controller->index(requestFor(['payment'=>'unpaid']))->getData();ok($d['rows']->total()===2&&(float)$d['stats']->unpaid===75.0,'Unpaid filter excludes paid and zero-fee jobs');
$d=$controller->index(requestFor(['payment'=>'paid']))->getData();ok($d['rows']->total()===1&&(float)$d['stats']->paid===100.0,'Paid filter matches wallet payments');
$d=$controller->index(requestFor(['kind'=>'pickup']))->getData();ok($d['rows']->total()===1&&(float)$d['stats']->earned===0.0,'Job type filter also filters summary');
$d=$controller->index(requestFor(['q'=>'FINANCE-TRACK-123','from'=>'2030-01-01']))->getData();ok($d['rows']->total()===0,'Date and tracking filters apply');
$pdf=$controller->index(requestFor(['format'=>'pdf']));ok(str_starts_with($pdf->getContent(),'%PDF')&&str_contains($pdf->getContent(),'Courier financial report'),'PDF exports financial report');
ok(Wallet::balance($fd)===$before,'Reports and exports never change wallet balance');
$links=(new CommonController)->links();ok(isset($links['Financial report'],$links['Wallet']),'Driver menu exposes separate report and wallet');
loginAs($fr);$data=$controller->index(requestFor())->getData();ok(!$data['isDriver']&&(float)$data['stats']->earned===40.0&&$data['rows']->total()===1,'Rider report excludes another courier earnings');
invalid(fn()=>$controller->index(requestFor(['from'=>'2026-11-06','to'=>'2026-01-01'])),'Invalid date range is rejected');
loginAs($buyer);try{$controller->index(requestFor());throw new RuntimeException('Buyer opened courier earnings');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Courier report is role restricted');}
echo 'PASS: '.($n-$begin)." courier financial report checks.\n";
