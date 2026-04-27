<?php

namespace App\Repository\payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;

class AdminPaymentRepository implements AdminPaymentInterface
{
    public function index()
    {
        $page = request()->get('page', 1);
        $cacheKey = "payments:index:page:{$page}";
        $payments = Cache::tags(['payments'])->remember(
            $cacheKey,
            now()->addMinutes(5),
            function () {
                return Payment::with('user:id,name,email')
                    ->select('id', 'transaction_id', 'status', 'user_id', 'currency', 'amount', 'paypal_order_id')
                    ->paginate(10);
            }
        );
        return $payments;
    }

    public function show($id)
    {
        return Payment::with('user')->find($id);
    }

    public function destroy($id)
    {
        return Payment::destroy($id);
    }

    public function update($request, $id)
    {
        $payment = Payment::find($id);
        $payment->update($request->all());

        return $payment;
    }
}
