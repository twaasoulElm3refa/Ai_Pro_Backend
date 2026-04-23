<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Repository\user\AdminUserRepositoryInterface as UserAdminUserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private UserAdminUserRepositoryInterface $userRepo
    ) {}

    public function index()
    {
        try {
            $users = $this->userRepo->index();

            return $this->success($users, 'Users fetched successfully.');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function store(UserRequest $request)
    {
        try {
            $data = $request->validated();
            if (request()->hasFile('image')) {
                $data['image'] = $request->file('image')->store('user');
            }
            $user = $this->userRepo->store($data);
            return $this->success(new UserResource($user), 'User created successfully.');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return $this->error($th->getMessage());
        }
    }

    public function update(UserUpdateRequest $request, $id)
    {
        try {
            $data = $request->validated();
            if (request()->hasFile('image')) {
                $data['image'] = $request->file('image')->store('user');
            }
            $user = $this->userRepo->update($data, $id);
            return $this->success(new UserResource($user), 'User created successfully.');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return $this->error($th->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $user = $this->userRepo->show($id);

            return $this->success($user, 'User fetched successfully.');
        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->userRepo->delete($id);

            return $this->success(null, 'User deleted successfully.');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }

    public function clearCache()
    {
        try {
            $this->userRepo->clearCache();

            return $this->success(null, 'Cache cleared successfully.');

        } catch (\Throwable $th) {
            return $this->error($th->getMessage());
        }
    }
}
