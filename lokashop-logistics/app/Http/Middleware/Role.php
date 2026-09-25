<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class Role { public function handle(Request $request, Closure $next, string $roles) {
 abort_unless(auth()->check() && in_array(auth()->user()->role,explode('|',$roles),true) && auth()->user()->approval_status==='approved' && auth()->user()->active,403);
 return $next($request);
} }
