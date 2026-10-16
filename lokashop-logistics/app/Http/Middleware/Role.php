<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class Role { public function handle(Request $request, Closure $next, string $roles) {
 abort_unless(auth()->check() && in_array(auth()->user()->role,explode('|',$roles),true) && auth()->user()->approval_status==='approved' && auth()->user()->active && auth()->user()->account_status==='active' && (!auth()->user()->verification_required || auth()->user()->email_verified_at),403);
 if(auth()->user()->last_seen_at && now()->diffInMinutes(auth()->user()->last_seen_at,true)>=30){\Illuminate\Support\Facades\DB::table('users')->where('id',auth()->id())->update(['online'=>0]);auth()->logout();$request->session()->invalidate();return redirect('/login')->withErrors(['email'=>'Session expired after 30 minutes of inactivity.']);}
 \Illuminate\Support\Facades\DB::table('users')->where('id',auth()->id())->update(['last_seen_at'=>now()]);
 return $next($request);
} }
