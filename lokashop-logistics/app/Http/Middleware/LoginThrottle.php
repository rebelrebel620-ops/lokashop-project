<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** Protect the web form as well as the separately throttled mobile login. */
class LoginThrottle
{
    public function handle(Request $request, Closure $next)
    {
        $email = $request->input('email');
        $email = is_string($email) ? mb_strtolower(trim($email)) : '';
        $ip = (string) $request->ip();
        $limits = [
            // Hash account identifiers so cache keys do not contain email addresses.
            ['login:account:'.hash('sha256', $email.'|'.$ip), 5],
            ['login:ip:'.hash('sha256', $ip), 30],
        ];

        foreach ($limits as [$key, $maximum]) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $retry = max(1, RateLimiter::availableIn($key));
                throw new ThrottleRequestsException(
                    'Too many sign-in attempts. Please try again in '.$retry.' seconds.',
                    null,
                    ['Retry-After' => $retry, 'Cache-Control' => 'no-store']
                );
            }
        }

        // Count every submitted attempt, including malformed and successful ones.
        // Changing email or making a successful login must not reset the IP budget.
        foreach ($limits as [$key]) {
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
