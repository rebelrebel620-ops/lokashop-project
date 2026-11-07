<?php
namespace App\Http\Controllers;
use App\Services\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class MobileWalletController {
 public function handle(Request $r,string $action,?int $id=null){$u=ApiToken::user($r->bearerToken());abort_unless(MobileWorkspaceController::allowed($u),401);Auth::setUser($u);$r->setUserResolver(fn()=>$u);$r->headers->set('Accept','application/json');return $id===null?app(WalletController::class)->$action($r):app(WalletController::class)->$action($r,$id);}
 public function index(Request $r){return $this->handle($r,'index');}
 public function transfer(Request $r){return $this->handle($r,'transfer');}
}
