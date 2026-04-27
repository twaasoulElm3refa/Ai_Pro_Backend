<?php

namespace App\Interfaces;


interface PaymentInterface
{
    public function pay(array $data): array;
    public function success(string $token): array;
    public function cancel(): array;
}
