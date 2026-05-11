<?php

namespace App\Repository\cost;

interface CostInterface
{
    public function paginate(array $filters): array;

    public function today(array $filters): array;

    public function find(int $id): mixed;

    public function destroy(int $id): bool;
}
