<?php

namespace App\Http\Controllers\api\admin\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\userResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AdminAuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        try {
            $request->all();
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return $this->unauthorized('Invalid credentials.');
            }
            if ($user->role != 'admin') {
                return $this->unauthorized('You are not an admin.');
            }

            $user->update(['last_seen' => now()]);

            $token = $user->createToken('rag-token')->plainTextToken;

            return $this->success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Logged in successfully.');

        } catch (\Exception $e) {
            Log::error('Login Error: '.$e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Something went wrong during login. Please try again later.', 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], 'Logged out successfully.');
    }

    public function profile()
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return $this->unauthorized('Unauthenticated.');
            }

            $cacheKey = "user_profile_{$user->id}";

            $profile = Cache::remember($cacheKey, 600, function () use ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'phone' => $user->phone,
                    'image' => $user->image,
                    'is_active' => $user->is_active,
                    'email' => $user->email,
                    'last_seen' => $user->last_seen,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                ];
            });

            return $this->success(
                ['user' => $profile],
                'Profile fetched successfully.'
            );

        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'Something went wrong while fetching profile.',
                500
            );
        }
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'phone' => 'nullable|numeric',
        ]);

        $user->update($validated);

        if ($request->hasFile('image')) {
            $user->image = $request->image->store('users', 'public');
            $user->save();
        }
        $this->clearProfileCache($user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => new userResource($user),
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'confirm_password' => 'required|string|same:new_password',
        ]);
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect.',
            ], 403);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully.',
        ]);
    }

    public function clearProfileCache($userId = null)
    {
        $userId = $userId ?? auth()->id();

        if (! $userId) {
            return;
        }

        Cache::forget("user_profile_{$userId}");
    }
}
