<?php

namespace App\Repository\user;

interface AdminUserRepositoryInterface
{
    public function index();
    public function show($id);
    public function store(array $data);
    public function update(array $data, $id);
    public function delete($id);
    public function clearCache();
}
