<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function googleLogin()
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'status' => 'success',
            'url' => $url,
        ]);
    }

    public function googleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName() ?: 'Google User',
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'is_active' => true,
                ]
            );

            $user->update([
                'last_seen' => now(),
            ]);

            $token = $user->createToken('google-auth-token')->plainTextToken;

            return redirect()->to(
                rtrim(config('app.frontend_url'), '/') . "/google-callback?token={$token}&role={$user->role}"
            );
        } catch (\Exception $e) {
            return redirect()->to(
                rtrim(config('app.frontend_url'), '/') . "/google-callback?error=" . urlencode($e->getMessage())
            );
        }
    }
}
