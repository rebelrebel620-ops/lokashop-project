<?php
namespace App\Http\Middleware;

use App\Services\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Bearer-token guard for the rider app. Same account rules as the web Role middleware. */
class ApiRider
{
    public function handle(Request $request, Closure $next)
    {
        $user = ApiToken::user($request->bearerToken());

        if (!$user || $user->role !== 'rider' || $user->approval_status !== 'approved' || !$user->active) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
}
