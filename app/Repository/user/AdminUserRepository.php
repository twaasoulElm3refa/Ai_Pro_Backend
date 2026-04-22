<?php

namespace App\Repository\user;

use App\Models\User;
use App\Repository\user\AdminUserRepositoryInterface as UserAdminUserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminUserRepository implements UserAdminUserRepositoryInterface
{
    private $cacheTime = 60;

    public function index($filters)
    {
        $page = $filters['page'] ?? 1;
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        $cacheKey = "users_page_{$page}_search_{$search}_status_{$status}";

        return Cache::tags(['users'])->remember(
            $cacheKey,
            $this->cacheTime,
            function () use ($search, $status) {

                return User::with([
                    'wallet:id,user_id,balance',
                    'payment:id,user_id,amount',
                    'walletTransaction:id,user_id,type,amount',
                ])
                    ->when($search, function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->when($status, function ($q) use ($status) {
                        $q->where('status', $status);
                    })
                    ->select('id', 'name', 'email', 'status')
                    ->paginate(10);
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
                ])
                    ->findOrFail($id);
            }
        );
    }

    public function store(array $data)
    {
        DB::beginTransaction();
        try {
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
            $user = User::findOrFail($id);
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
        $user->delete();

        $this->clearCache();

        return true;
    }

    public function clearCache()
    {
        Cache::tags(['users'])->flush();
    }
}
