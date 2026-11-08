<?php
require __DIR__.'/delivery_proof_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\{Wallet,WalletEarnings};
use App\Http\Controllers\{OperationsController,CenterFinancialController,CommonController};
$begin=$n;
function codInvalid(callable $fn,string $label){try{$fn();}catch(Illuminate\Validation\ValidationException $e){ok(true,$label);return;}throw new RuntimeException('FAIL: '.$label);}
$ops=new OperationsController;loginAs($rider);$expected=(float)DB::table('cod_collections')->where('rider_id',$rider)->whereNull('remittance_id')->sum('amount');ok($expected>0,'Actual scan/proof delivery fixture created rider COD collections');
$rb=Wallet::balance($rider);$cb=Wallet::balance($company);$request=requestFor(['amount'=>$expected-0.5,'reference'=>'COD-CHECK-SHORT','_request_key'=>(string)Str::uuid()]);$result=$ops->remit($request)->getData(true);$rid=$result['data'];$ops->remit($request);
$row=DB::table('remittances')->find($rid);ok($row->status==='short'&&(float)$row->expected===$expected&&(float)$row->amount===$expected-0.5,'Shortage is accurately recorded');ok(DB::table('remittances')->where('rider_id',$rider)->count()===1,'Submission retry never duplicates remittance');
codInvalid(fn()=>$ops->remit(requestFor(['amount'=>1,'reference'=>'DUPLICATE','_request_key'=>(string)Str::uuid()])),'Unresolved remittance blocks another submission');
loginAs($company);codInvalid(fn()=>$ops->confirm(requestFor(['id'=>$rid,'reason'=>'Received short cash remittance'])),'Center cannot confirm a shortage');
$foreign=user('sorting_center','codforeigncenter');loginAs($foreign);try{$ops->confirm(requestFor(['id'=>$rid,'reason'=>'Trying another center record']));throw new RuntimeException('Foreign center confirmed COD');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Another center cannot confirm rider COD');}
loginAs($rider);codInvalid(fn()=>$ops->topup(requestFor(['id'=>$rid,'amount'=>'0.51','reference'=>'TOO-MUCH','_request_key'=>(string)Str::uuid()])),'Top-up cannot exceed shortage');
$topup=requestFor(['id'=>$rid,'amount'=>'0.50','reference'=>'SHORTAGE-RESOLVED','_request_key'=>(string)Str::uuid()]);$ops->topup($topup);$ops->topup($topup);
$row=DB::table('remittances')->find($rid);ok($row->status==='pending'&&(float)$row->amount===$expected,'Exact top-up resolves shortage once');
loginAs($company);$confirm=requestFor(['id'=>$rid,'reason'=>'Cash counted and received in full']);$ops->confirm($confirm);$ops->confirm($confirm);ok(DB::table('remittances')->find($rid)->status==='confirmed','Center confirms completed rider remittance');
ok(Wallet::balance($rider)===$rb&&Wallet::balance($company)===$cb,'COD remittance does not change courier earnings or demo wallet money');
loginAs($rider);codInvalid(fn()=>$ops->remit(requestFor(['amount'=>1,'reference'=>'NO-BALANCE','_request_key'=>(string)Str::uuid()])),'Already remitted cash cannot be submitted again');
$riderCod=$ops->panel(requestFor(),'cod')->getData();ok(str_contains($riderCod['title'],'COD Collections & Remittances'),'Rider COD page remains accessible with clearer title');
$apiCod=(new App\Http\Controllers\MobileRiderController)->cod(requestFor())->getData(true);ok((float)$apiCod['unremitted']===0.0&&$apiCod['open']===null,'Rider COD API shows no outstanding cash after confirmed remittance');
$method=new ReflectionMethod(App\Http\Controllers\RiderApiController::class,'actions');$codAction=(object)['status'=>'accepted','kind'=>'delivery','parcel_status'=>'OUT_FOR_DELIVERY','pay_method'=>'cod'];$walletAction=clone $codAction;$walletAction->pay_method='wallet';
$api=new App\Http\Controllers\RiderApiController;ok($method->invoke($api,$codAction)[0]['needs_cod_amount']===true&&$method->invoke($api,$walletAction)[0]['needs_cod_amount']===false,'Rider API requires cash for COD and no cash for prepaid wallet delivery');
// Sorting center report reflects income, cost, funding owed, COD and confirmed cash separately.
loginAs($company);$controller=new CenterFinancialController;$view=$controller->index(requestFor());$data=$view->getData();
$gross=(float)DB::table('cod_collections')->where('company_id',$company)->sum('amount');$received=(float)DB::table('remittances')->where('company_id',$company)->where('status','confirmed')->sum('amount');
ok((float)$data['stats']->cod_collected===$gross&&(float)$data['stats']->cod_received===$received,'Center financial report reflects rider collections and confirmed cash');
ok($data['rows']->perPage()===12,'Center financial report paginates at 12');
$html=$view->render();ok(str_contains($html,'Financial report')&&str_contains($html,'COD Collections &amp; Remittances'),'Center report renders with separate COD link');
$before=Wallet::balance($company);$pdf=$controller->index(requestFor(['format'=>'pdf']));ok(str_starts_with($pdf->getContent(),'%PDF')&&str_contains($pdf->getContent(),'Sorting center financial report'),'Center financial PDF exports successfully');ok(Wallet::balance($company)===$before,'Financial report and export do not move money');
$d=$controller->index(requestFor(['type'=>'cod_remittance','status'=>'confirmed']))->getData();ok($d['rows']->total()===1&&(float)$d['stats']->income===0.0&&(float)$d['stats']->cod_received===$expected,'Type/status filters apply to totals and rows');
$d=$controller->index(requestFor(['from'=>'2030-01-01']))->getData();ok($d['rows']->total()===0&&$d['balance']===Wallet::balance($company)/100,'Filtered activity excludes other dates while current balance remains all-date');
$links=(new CommonController)->links();ok(isset($links['Financial report'],$links['COD Collections & Remittances']),'Center menu includes report and renamed COD page');
loginAs($foreign);$data=$controller->index(requestFor())->getData();ok($data['rows']->total()===0,'Center report cannot expose another center finances');
loginAs($rider);try{$controller->index(requestFor());throw new RuntimeException('Rider accessed center finances');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Center report is sorting-center only');}
echo 'PASS: '.($n-$begin)." center financial and rider COD checks.\n";
