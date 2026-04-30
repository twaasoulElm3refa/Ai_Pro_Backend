<?php

namespace App\Http\Controllers\api\admin\payment;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\payment\AdminPaymentInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminPaymentController extends Controller
{
    use ApiResponse;

    private $paymentRepo;

    public function __construct(AdminPaymentInterface $payment)
    {
        $this->paymentRepo = $payment;
    }

    public function index()
    {
        try {
            $payments = $this->paymentRepo->index();
            return $this->success($payments, 'Payments fetched successfully.');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }

    public function show($id)
    {
        try {
            $payment = $this->paymentRepo->show($id);
            return $this->success($payment, 'Payment fetched successfully.');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $payment = $this->paymentRepo->update($request, $id);
            return $this->success($payment, 'Payment updated successfully.');
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $this->paymentRepo->destroy($id);
            return $this->success(null, 'Payment deleted successfully.');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }
}
