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
                        'master_key' => bcrypt($masterKey)
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
                        'master_key' => bcrypt($masterKey)
                    ]
                );
            }

            if ($appUser) {
                Auth::login($appUser);

                return redirect()->route('messenger')->with('success', 'Welcome '.$appUser->name);
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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth')->with('success', 'Logged out successfully');
    }
}
