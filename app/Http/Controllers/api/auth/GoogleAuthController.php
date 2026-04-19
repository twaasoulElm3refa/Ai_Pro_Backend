<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function googleLogin()
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        return response()->json([
            'status' => 'success',
            'url' => $url
        ]);
    }

    public function googleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'password' => Hash::make('password'),
                    'role' => 'user'
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;
            return redirect()->to(env('FRONTEND_URL') . '/auth?token=' . $token . '&role=' . $user->role);




        } catch (\Exception $e) {
            return redirect()->to(env('FRONTEND_URL', 'http://localhost:8000') . '/?error=' . urlencode($e->getMessage()));
        }
    }

}
