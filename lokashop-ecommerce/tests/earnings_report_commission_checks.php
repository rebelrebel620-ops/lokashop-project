<?php
require __DIR__.'/wallet_earnings_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\{Wallet,WalletEarnings};
use App\Http\Controllers\{MarketController,SellerSalesController,WalletController};
$begin=$n;
$id=walletOrder('COMPLETED');DB::table('orders')->where('id',$id)->update(['store_discount'=>20,'commission'=>8]);DB::table('parcels')->where('order_id',$id)->update(['tracking_code'=>'REPORT-NET-ONE']);
loginAs($seller);$balance=Wallet::balance($seller);$r=requestFor(['q'=>'REPORT-NET-ONE']);$report=(new MarketController)->panel($r,'earnings');$data=$report->getData();
ok($report->name()==='seller-sales'&&$data['earningsReport']===true,'Seller Earnings opens a report instead of redirecting to wallet');
ok((float)$data['stats']->net_earnings===72.0&&(float)$data['stats']->earned_commission===8.0,'Earnings report deducts store discount and commission');
ok($data['orders']->count()===1&&$data['orders']->first()->seller_id===$seller,'Report search stays scoped to seller');
$html=$report->render();ok(str_contains($html,'Earnings report')&&str_contains($html,'action="/tools/earnings"')&&str_contains($html,'Open Wallet'),'Report keeps its own filters and a separate wallet link');
ok(Wallet::balance($seller)===$balance,'Reading the earnings report never changes wallet balance');
$pdf=(new MarketController)->panel(requestFor(['q'=>'REPORT-NET-ONE','format'=>'pdf']),'earnings');ok(str_starts_with($pdf->getContent(),'%PDF')&&str_contains($pdf->headers->get('Content-Disposition'),'earnings'),'Earnings report exports a PDF');
$sales=(new SellerSalesController)->index(requestFor());ok($sales->getData()['earningsReport']===false,'Sales report retains its normal mode');
// Previously completed, uncredited admin commissions can be collected once.
loginAs($admin);$controller=new WalletController;$available=(float)WalletEarnings::availableAdmin()->sum('commission');ok($available>0,'Old completed-order commission is available to collect');
$before=Wallet::balance($admin);$r=requestFor(['password'=>'Test@1234','_request_key'=>(string)Str::uuid()]);$controller->collectCommission($r);$controller->collectCommission($r);
ok(Wallet::balance($admin)===$before+Wallet::cents($available),'Available admin commission moves to wallet exactly once');
ok((float)WalletEarnings::availableAdmin()->sum('commission')===0.0,'Credited commissions are excluded from future collection');
loginAs($admin2);$otherBefore=Wallet::balance($admin2);$controller->collectCommission(requestFor(['password'=>'Test@1234','_request_key'=>(string)Str::uuid()]));ok(Wallet::balance($admin2)===$otherBefore,'Another admin cannot collect the same commission twice');
$id=walletOrder('DELIVERED');ok(!WalletEarnings::availableAdmin()->where('id',$id)->exists(),'Delivered order awaits completion before commission is collectible');
$id=walletOrder('COMPLETED','cod','due_on_delivery');ok(!WalletEarnings::availableAdmin()->where('id',$id)->exists(),'Unpaid order commission cannot be collected');
$id=walletOrder('COMPLETED');DB::table('orders')->where('id',$id)->update(['refunded_at'=>now()]);ok(!WalletEarnings::availableAdmin()->where('id',$id)->exists(),'Refunded order commission cannot be collected');
loginAs($seller);try{$controller->collectCommission(requestFor(['password'=>'Test@1234','_request_key'=>(string)Str::uuid()]));throw new RuntimeException('Seller collected admin commission');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===403,'Only admin may collect commission');}
echo 'PASS: '.($n-$begin)." earnings report and admin commission checks.\n";
