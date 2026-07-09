<?php

namespace App\Http\Middleware;

use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DetectConcurrentLogins
{
    public function __construct(private UserService $userService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $userId = (string) $user->_id;
            $currentSessionId = $request->session()->getId();

            // Compare against session ID stored in Redis (set at login time)
            // instead of fetching the user row from MongoDB on every request.
            $storedSessionId = $this->userService->getSession($userId);

            if ($storedSessionId && $storedSessionId !== $currentSessionId) {
                $location = $user->last_login_location ?? 'Unknown';
                $browser  = $user->last_login_browser  ?? 'Unknown Browser';
                $avatar   = $user->avatar;

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('auth')->with([
                    'error'  => "Another login detected. You have been logged out. New login from: {$location} using {$browser}.",
                    'avatar' => $avatar,
                ]);
            }
        }

        return $next($request);
    }
}
