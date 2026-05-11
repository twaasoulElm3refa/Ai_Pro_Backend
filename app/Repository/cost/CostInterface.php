<?php

namespace App\Repository\cost;

interface CostInterface
{
    public function index();
    public function today();
    public function show($id);
    public function destroy($id);
}
