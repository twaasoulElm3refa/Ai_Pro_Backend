<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    use apiResponse;
    public function index()
    {
        $all = Cache::remember('users_latest_10', 1200, function () {
            return User::latest()->where('is_active', 1)->take(10)->get();
        });

        return $this->apiResponse($all, 'All User');
    }
    public function all()
    {
        $cacheKey = 'users_all_page_' . request('page', 1);
        $all = Cache::remember($cacheKey, 1200, function () {
            return User::latest()->paginate(20);
        });

        return $this->apiResponse($all, 'All User');
    }
    public function count()
    {
        $all = User::count();
        return $this->apiResponse($all, 'User Count');
    }

    public function show()
    {
        $user = User::find(request('id'));
        if (!$user) {
            return $this->apiResponse([], 'user Not Found');
        }
        return $this->apiResponse($user, 'user');

    }

    public function products()
    {
        return $this->apiResponse([], 'Products');
    }

    public function create(UserRequest $request)
    {
        $request->validated();

        $user = User::create([
            'name' => $request['name'] ?? '',
            'phone' => $request['phone'] ?? '',
            'email' => $request['email'] ?? '',
            'password' => $request['password'] ?? '',
            'slug' => $request['name'] . '-' . time(),
        ]);
        return $this->apiResponse($user, 'user Created Successfully');

    }

    public function update(Request $request)
    {
        $data = $request->all();
        $user = User::find(request('id'));
        if (!$user) {
            return $this->apiResponse([], 'user Not Found');
        }
        $user->update($data);
        return $this->apiResponse($user, 'user Updated Successfully');
    }

    public function destroy()
    {
        $user = User::find(request('id'));
        if (!$user) {
            return $this->apiResponse([], 'user Not Found');
        }
        $user->delete();
        return $this->apiResponse($user, 'user Deleted Successfully');
    }
}
