<?php

namespace App\Repository\payment;

interface AdminPaymentInterface
{
    public function index();
    public function show($id);
    public function update($request,$id);
    public function destroy($id);
}
