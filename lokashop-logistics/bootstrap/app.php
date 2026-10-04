<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware): void {
   $middleware->alias(['role'=>\App\Http\Middleware\Role::class,'rider.api'=>\App\Http\Middleware\ApiRider::class]);
 })
 ->withExceptions(function (Exceptions $exceptions): void {
   // The mobile app always gets JSON errors (abort(403), validation, 404...).
   $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
 })->create();
