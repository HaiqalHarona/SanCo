<?php

namespace App\Http\Controllers;

use App\Models\User;
use FurqanSiddiqui\BIP39\BIP39;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{

    public function redirectProvider($provider)
    {
        // Force account selection for both Google and GitHub to prevent auto-login
        if ($provider === 'google' || $provider === 'github') {
            return Socialite::driver($provider)
                ->with(['prompt' => 'select_account'])
                ->redirect();
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callbackRequest($provider)
    {
        try {
            $user = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('auth')->with('error', 'Login with '.ucfirst($provider).' failed. Please try again later.');
        }

        $appUser = null;

        try {
            $masterKey = implode(' ', BIP39::Generate(24)->words);

            if ($provider == 'google') {
                $email = $user->getEmail();
                $providerId = $user->getId();

                if (empty($email)) {
                    return redirect()->route('auth')->with('error', "We could not get your email address from $provider. Please make your email public on Google or register manually.");
                }

                $appUser = User::firstOrCreate(
                    [
                        'google_id' => $providerId,
                        'email' => $email,
                    ],
                    [
                        'name' => $user->getName(),
                        'avatar' => $user->getAvatar(),
                        'user_tag' => $this->generateUniqueTag('SanCo'),
                        'master_key' => bcrypt($masterKey),
                    ]
                );
            } elseif ($provider == 'github') {
                $providerId = $user->getId();

                $appUser = User::firstOrCreate(
                    [
                        'github_id' => $providerId,
                    ],
                    [
                        'name' => $user->getName() ?? $user->getNickname(),
                        'email' => $user->getEmail(),
                        'avatar' => $user->getAvatar(),
                        'user_tag' => $this->generateUniqueTag('SanCo'),
                        'master_key' => bcrypt($masterKey),
                    ]
                );
            }

            if ($appUser) {
                // Capture user metadata for security monitoring
                $ip = request()->ip();
                $browser = request()->header('User-Agent');
                $location = 'Unknown';

                try {
                    // Attempt to resolve IP to a physical location
                    $response = file_get_contents("http://ip-api.com/json/{$ip}?fields=status,message,country,city");
                    if ($response) {
                        $data = json_decode($response, true);
                        if ($data && $data['status'] === 'success') {
                            $location = "{$data['city']}, {$data['country']}";
                        }
                    }
                } catch (\Exception $e) {
                    // Fail silently for location fetch to avoid blocking login
                }

                // Perform login and regenerate session ID to prevent fixation attacks
                Auth::login($appUser);
                session()->regenerate();

                // Store the NEW session ID in the database to track concurrent logins
                $appUser->update([
                    'current_session_id' => session()->getId(),
                    'last_login_ip' => $ip,
                    'last_login_browser' => $browser,
                    'last_login_location' => $location,
                ]);

                if ($appUser->wasRecentlyCreated) {
                    return redirect()->route('messenger')
                        ->with('new_master_key', $masterKey)
                        ->with('success', 'Welcome! Your account has been created and secured.');
                }
                return redirect()->route('messenger')->with('success', 'Welcome '. $appUser->name);
            }

            return redirect()->route('auth')->with('error', 'Authentication provider not recognized.');
        } catch (\Exception $e) {
            return redirect()->route('auth')->with('error', 'An error occurred during authentication: '.$e->getMessage());
        }
    }

    protected function generateUniqueTag($prefix = 'user')
    {
        $unique = false;
        $tag = '';

        while (! $unique) {
            // Generate a random unique tag like 'goog_a1b2c3d4e5' or 'ghub_a1b2c3d4e5'
            $tag = $prefix.'_'.Str::lower(Str::random(10));

            if (! User::where('user_tag', $tag)->exists()) {
                $unique = true;
            }
        }

        return $tag;
    }

    public function logout(Request $request)
    {
        // Get the current user ID before logout to clear specific session keys if needed
        $userId = Auth::id();
        
        Auth::logout();
        
        // Invalidate the session and regenerate the token to prevent session fixation
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear any specific session data that might linger
        $request->session()->forget([
            'new_master_key',
            'e2e_private_' . $userId,
            'e2e_public_' . $userId
        ]);

        return redirect()->route('auth')->with('success', 'Logged out successfully');
    }
}
