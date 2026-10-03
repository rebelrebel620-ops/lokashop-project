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

        $user = DB::table('users')->where('remember_token', $token)->first();

        if (!$user || $user->role !== 'buyer' || $user->approval_status !== 'approved' || !$user->active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::onceUsingId($user->id);

        return $next($request);
    }
}
