<?php
// Isolated SQLite checks. Does not connect to the configured MySQL database.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Hash,Cache,Auth};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use App\Http\Controllers\MobileWorkspaceController;
use App\Models\User;
use App\Services\ApiToken;
config(['app.key'=>'base64:'.base64_encode(str_repeat('T',32)),
 'database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],
 'session.driver'=>'array','cache.default'=>'file','cache.stores.file.path'=>sys_get_temp_dir().'/loka-mobile-test-'.bin2hex(random_bytes(6)),
 'hashing.bcrypt.rounds'=>4]);
DB::purge();
Schema::create('users',function(Blueprint $t){
 $t->id();foreach(['name','email','password','role','courier_type','approval_status','account_status'] as $c)$t->string($c);
 foreach(['phone','vehicle','profile_photo','remember_token'] as $c)$t->string($c)->nullable();
 $t->boolean('active')->default(true);$t->boolean('online')->default(false);$t->boolean('verification_required')->default(true);
 $t->timestamp('email_verified_at')->nullable();$t->timestamp('last_seen_at')->nullable();$t->timestamps();
});
$n=0;
function good($value,$label){global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;}
function req(array $fields=[],?string $token=null):Request{global $app;$r=Request::create('/test','POST',$fields,[],[],['HTTP_ACCEPT'=>'application/json']+($token?['HTTP_AUTHORIZATION'=>'Bearer '.$token]:[]));$r->setLaravelSession($app['session']->driver());$r->session()->start();$app->instance('request',$r);return $r;}
function blocked(callable $fn,int $status,string $label){try{$fn();throw new RuntimeException('Expected rejection: '.$label);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){good($e->getStatusCode()===$status,$label);}}
function account($name,$role,$type='rider'){return User::create(['name'=>$name,'email'=>$name.'@example.test','password'=>Hash::make('Example@2026'),'role'=>$role,'courier_type'=>$type,'approval_status'=>'approved','account_status'=>'active','active'=>true,'email_verified_at'=>now()]);}
$controller=new MobileWorkspaceController();$center=account('center','sorting_center');$driver=account('driver','rider','driver');$buyer=account('buyer','buyer');
$login=$controller->login(req(['email'=>$center->email,'password'=>'Example@2026']))->getData(true);
good($login['rider']['role']==='sorting_center','Sorting center can use mobile login');
good($controller->login(req(['email'=>$buyer->email,'password'=>'Example@2026']))->getStatusCode()===403,'Buyer excluded from logistics mobile');
good($controller->login(req(['email'=>$center->email,'password'=>'wrong']))->getStatusCode()===422,'Wrong password rejected');
good($controller->login(req(['email'=>$driver->email,'password'=>'Example@2026']))->getData(true)['rider']['courier_type']==='driver','Driver retains courier type');
$ticket=$controller->ticket(req([],$login['token']))->getData(true)['ticket'];
$response=$controller->enter(req(['ticket'=>$ticket]));
good(str_ends_with($response->getTargetUrl(),'/dashboard'),'Center enters its web dashboard');
good(Auth::id()===$center->id,'Web session belongs to the exact mobile user');
blocked(fn()=>$controller->enter(req(['ticket'=>$ticket])),403,'Ticket cannot be reused');
$ticket=$controller->ticket(req([],$login['token']))->getData(true)['ticket'];
DB::table('users')->where('id',$center->id)->update(['account_status'=>'suspended']);
blocked(fn()=>$controller->enter(req(['ticket'=>$ticket])),403,'Account state rechecked when entering web');
blocked(fn()=>$controller->ticket(req([],$login['token'])),401,'Suspended center cannot issue tickets');
DB::table('users')->where('id',$center->id)->update(['account_status'=>'active']);
$ticket=$controller->ticket(req([],$login['token']))->getData(true)['ticket'];
$controller->logout(req([],$login['token']));
blocked(fn()=>$controller->enter(req(['ticket'=>$ticket])),403,'Logout invalidates outstanding handoff');
good(ApiToken::user($login['token'])===null,'Logout revokes native token');
[$centerToken]=ApiToken::issue(User::find($center->id));
$guard=new App\Http\Middleware\ApiRider();
good($guard->handle(req([],$centerToken),fn()=>response('allowed'))->getStatusCode()===401,'Sorting center cannot use rider-only API');
echo "PASS: {$n} mobile workspace checks. SQLite only.\n";
