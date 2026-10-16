<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\{DB,Mail};
use App\Http\Controllers\{AppealController,AccountController,AdminController};
use Illuminate\Validation\ValidationException;
Mail::swap(new class {function raw($text,$callback){$callback(new class{function to($x){return $this;}function subject($x){return $this;}});}});
$admin=user('admin','appealadmin');$target=user('buyer','appealbuyer');loginAs($admin);$status=new AccountController();
$status->status(requestFor(['status'=>'suspended','reason'=>'Suspension for regression check']),$target);
loginAs($target);$appeals=new AppealController();$appeals->submit(requestFor(['message'=>'Please review my suspension and restore my account.']));
ok(DB::table('account_appeals')->where('user_id',$target)->where('status','pending')->count()===1,'Suspended buyer submits appeal');
try{$appeals->submit(requestFor(['message'=>'This duplicate appeal should never be accepted.']));throw new RuntimeException('Duplicate appeal accepted');}catch(ValidationException $e){ok(true,'Duplicate pending appeal rejected');}
$r=requestFor();$response=(new App\Http\Middleware\Role())->handle($r,fn()=>throw new RuntimeException('Buyer operations ran while suspended'),'buyer');ok($response->isRedirect(),'Suspended buyer routed to appeal');
loginAs($admin);$id=DB::table('account_appeals')->where('user_id',$target)->value('id');$appeals->resolve(requestFor(['decision'=>'approved','resolution'=>'Review complete, account restored.']),$id);
ok(DB::table('users')->find($target)->account_status==='active','Approved appeal restores account');
$status->status(requestFor(['status'=>'archived','reason'=>'Archive regression check']),$target);$controller=new AdminController();
$view=$controller->users(requestFor());ok(!$view->getData()['users']->contains('id',$target),'Archived user hidden from default list');
$view=$controller->users(requestFor(['state'=>'archived','role'=>'buyer']));ok($view->getData()['users']->contains('id',$target),'Archive and buyer filters combine');
$view=$controller->users(requestFor(['state'=>'archived','role'=>'seller']));ok(!$view->getData()['users']->contains('id',$target),'Combined filters exclude wrong role');
$status->status(requestFor(['status'=>'banned','reason'=>'Permanent ban regression check']),$target);
try{$status->status(requestFor(['status'=>'active','reason'=>'Attempt restore banned']),$target);throw new RuntimeException('Ban restored');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===422,'Banned account cannot be restored');}
loginAs($target);try{$appeals->form();throw new RuntimeException('Banned appeal page allowed');}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ok($e->getStatusCode()===403,'Banned account cannot appeal');}
echo "PASS: account state and appeal checks.\n";
