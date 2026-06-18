<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->web(append: [
            \App\Http\Middleware\DetectConcurrentLogins::class,
        ]);
        $middleware->redirectTo(
            guests: fn () => session()->flash('error', 'Please log in to access your chats.') ? '/' : '/'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle session expiration (HTTP 419) by redirecting to login with a message
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            return redirect()->route('auth')->with('error', 'Your session has expired. Please log in again.');
        });
    })->create();