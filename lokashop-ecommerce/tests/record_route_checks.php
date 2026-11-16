<?php
require __DIR__.'/workflow_checks.php';
use Illuminate\Http\Request;use Illuminate\Support\Facades\{Auth,DB};
$logistics=str_contains(getcwd(),'logistics');$roles=$logistics?['sorting_center'=>$company,'rider'=>$rider,'logistics_admin'=>user('logistics_admin','lastadmin')]:['buyer'=>$buyer,'seller'=>$seller,'admin'=>user('admin','lastadmin')];$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$rows=[];
foreach($app['router']->getRoutes() as $route){$uri=$route->uri();if(!in_array('GET',$route->methods())||str_starts_with($uri,'api/')||!str_contains($uri,'{id}'))continue;
 foreach($roles as $role=>$uid)foreach(['99999999','not-a-number'] as $id){$path='/'.str_replace(['{id}','{index}'],[$id,'0'],$uri);if(str_contains($path,'{'))continue;loginAs($uid);$r=Request::create($path,'GET',[],[],[],['HTTP_ACCEPT'=>'application/json','HTTP_HOST'=>'localhost']);$r->setLaravelSession($app['session']->driver());$r->setUserResolver(fn()=>Auth::user());$app->instance('request',$r);try{$res=$kernel->handle($r);$status=$res->getStatusCode();$msg=$status>=500?substr($res->getContent(),0,420):null;}catch(Throwable $e){$status=500;$msg=$e->getMessage();}$rows[]=['role'=>$role,'path'=>$path,'status'=>$status,'error'=>$msg,'body'=>$res->getContent()];}
}
foreach($rows as $row){ok(in_array($row['status'],[403,404],true)||($row['path']==='/product/99999999/state'&&$row['status']===200&&(json_decode($row['body'],true)['available']??null)===false),$row['role'].' rejects absent or malformed record URL '.$row['path']);}
echo 'PASS: '.count($rows)." missing and malformed record route checks.\n";
