<?php

namespace App\Repository\tools;


interface SubToolInterface
{
    public function index($id);
    public function show($id);
    public function store(array $data);
    public function update(array $data,$id);
    public function destroy($id);
}
