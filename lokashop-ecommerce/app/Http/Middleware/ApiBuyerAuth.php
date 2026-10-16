<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiBuyerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = DB::table('users')->where('remember_token', hash('sha256',$token))->where('api_token_expires_at','>',now())->first();

        $appealOnly=$user && $user->account_status==='suspended' && $request->is('api/appeal','api/logout');
        if (!$user || $user->role !== 'buyer' || $user->approval_status !== 'approved' || (!$appealOnly&&(!$user->active||$user->account_status!=='active')) || (($user->verification_required ?? false) && !$user->email_verified_at)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::onceUsingId($user->id);
        $request->setUserResolver(fn()=>Auth::user());
        DB::table('users')->where('id',$user->id)->update(['api_token_expires_at'=>now()->addMinutes(30),'last_seen_at'=>now()]);

        return $next($request);
    }
}
