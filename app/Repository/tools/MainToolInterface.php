<?php

namespace App\Repository\tools;

interface MainToolInterface
{
    public function index();
    public function show($id);
    public function showBySlug($slug);
    public function store(array $data);
    public function update(array $data,$id);
    public function destroy($id);
}
