<?php

namespace App\Repository\Register;

use App\Models\User;

class UserRepositoryImpl implements UserRepository
{
    public function create(array $data)
    {
        return User::create($data);
    }

    public function update($user, array $data)
    {
        return $user->update($data);
    }
}
