<?php

namespace App\Repository\Register;

interface UserRepository
{
    public function create(array $data);
    public function update($user, array $data);
}
