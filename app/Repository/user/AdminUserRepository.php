<?php

namespace App\Repository\user;

use App\Models\User;
use App\Repository\user\AdminUserRepositoryInterface as UserAdminUserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminUserRepository implements UserAdminUserRepositoryInterface
{
    private $cacheTime = 60;

    public function index()
    {
        $page = request()->get('page', 1);

        $cacheKey = "users_page_{$page}";

        return Cache::tags(['users'])->remember(
            $cacheKey,
            $this->cacheTime,
            function () {

                $start = microtime(true);

                $data = User::with([
                    'wallet:id,user_id,balance',
                    'payment:id,user_id,amount',
                    'walletTransaction:id,user_id,type,points',
                ])
                    ->select('id', 'name', 'email', 'role', 'is_active', 'last_seen', 'deleted_at')
                    ->paginate(10);

                $end = microtime(true);

                $duration = round(($end - $start) * 1000, 2); // milliseconds

                logger()->info('Users Query Execution Time', [
                    'time_ms' => $duration,
                    'page' => request()->get('page', 1),
                    'cached' => ! empty($data),
                ]);

                return $data;
            }
        );
    }

    public function show($id)
    {
        $cacheKey = "user_{$id}";

        return Cache::tags(['users'])->remember(
            $cacheKey,
            $this->cacheTime,
            function () use ($id) {
                return User::with([
                    'wallet',
                    'payment',
                    'walletTransaction',
                ])->withTrashed()
                    ->findOrFail($id);
            }
        );
    }

    public function store(array $data)
    {
        DB::beginTransaction();
        try {
            $data['is_active'] = true;
            $data['is_verified'] = true;
            $user = User::create($data);
            $this->clearCache();
            DB::commit();

            return $user;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();

        try {
            $user = User::withTrashed()->findOrFail($id);
            if ($user->trashed()) {
                $user->restore();
            }
            $user->update($data);
            $this->clearCache();
            DB::commit();

            return $user->fresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = false;
        $user->save();
        $user->delete();

        $this->clearCache();

        return true;
    }

    public function clearCache()
    {
        Cache::tags(['users'])->flush();
    }
}
