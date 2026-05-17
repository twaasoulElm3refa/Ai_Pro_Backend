<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\userResource;
use App\Models\FreeCreditClaim;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ProfileController extends Controller
{
    use ApiResponse;

    public function profile(Request $request)
    {
        try {
            $user = auth()->user();

            if (! $user) {
                return $this->unauthorized('Unauthenticated.');
            }

            DB::transaction(function () use ($user) {
                $ipAddress = request()->ip();
                $deviceFingerprint = request()->header('X-Device-Fingerprint');
                $userAgent = request()->userAgent();

                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'ip_address' => $ipAddress,
                        'balance' => 0,
                        'uuid' => Str::uuid(),
                    ]
                );

                $alreadyClaimed = FreeCreditClaim::where('user_id', $user->id)
                    ->orWhere('email', $user->email)
                    ->when($deviceFingerprint, function ($query) use ($deviceFingerprint) {
                        $query->orWhere('device_fingerprint', $deviceFingerprint);
                    })
                    ->orWhere('ip_address', $ipAddress)
                    ->exists();

                $canGiveFreeCredit =
                    ! $alreadyClaimed &&
                    ! empty($user->email_verified_at);

                if ($canGiveFreeCredit) {
                    $wallet->increment('balance', 1000000);

                    $wallet->update([
                        'ip_address' => $ipAddress,
                    ]);

                    FreeCreditClaim::create([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip_address' => $ipAddress,
                        'device_fingerprint' => $deviceFingerprint,
                        'user_agent' => $userAgent,
                        'amount' => 1000000,
                        'claimed_at' => now(),
                    ]);
                }
            });

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

        $currentTokenId = $request->user()->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password updated successfully.',
        ]);
    }

    public function deleteAccount()
    {
        try {
            $user = auth()->user();
            $user->update(['is_active' => false, 'email' => 'deleted'.$user->email.$user->id]);
            $this->clearProfileCache($user->id);
            $user->tokens()->delete();
            $user->delete();

            return $this->success([], 'Account deleted successfully.');

        } catch (Throwable $e) {
            return $this->error('Something went wrong while deleting account.', 500);
        }
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
