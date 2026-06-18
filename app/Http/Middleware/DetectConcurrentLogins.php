<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DetectConcurrentLogins
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $currentSessionId = $request->session()->getId();

            // If the session ID in the browser doesn't match the one in the DB, 
            // it means a newer login has happened elsewhere.
            if ($user->current_session_id && $user->current_session_id !== $currentSessionId) {
                $location = $user->last_login_location ?? 'Unknown';
                $browser = $user->last_login_browser ?? 'Unknown Browser';

                // Log the old session out immediately
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Redirect with metadata about the new login to notify the user
                return redirect()->route('auth')->with('error', "Another login detected. You have been logged out. New login from: {$location} using {$browser}.");
            }
        }

        return $next($request);
    }
}
