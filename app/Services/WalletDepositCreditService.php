<?php

namespace App\Services;

use App\Models\Wallet;
use OverflowException;

class WalletDepositCreditService
{
    public function apply(Wallet $wallet, int $points): array
    {
        $before = (int) $wallet->balance;
        $paybackBefore = max(0, (int) $wallet->payback_balance);
        $repaid = min($points, $paybackBefore);
        $credited = $points - $repaid;
        if ($points <= 0 || $before > PHP_INT_MAX - $credited) {
            throw new OverflowException('Wallet deposit points are invalid.');
        }

        $wallet->forceFill([
            'balance' => $before + $credited,
            'payback_balance' => $paybackBefore - $repaid,
        ])->save();

        return [
            'balance_before' => $before,
            'balance_after' => $before + $credited,
            'payback_before' => $paybackBefore,
            'payback_after' => $paybackBefore - $repaid,
        ];
    }
}
