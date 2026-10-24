<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeePaymentRequest;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeePaymentController extends Controller
{
    public function store(FeePaymentRequest $request, Fee $fee)
    {
        $payment = DB::transaction(function () use ($request, $fee) {
            // Voucher ko lock kar ke balance dobara dekhte hain (double-click se zyada paisa na likha jaye)
            $locked  = Fee::whereKey($fee->id)->lockForUpdate()->firstOrFail();
            $balance = round($locked->balance, 2);
            $amount  = round((float) $request->input('amount'), 2);

            if ($balance <= 0) {
                throw ValidationException::withMessages(['amount' => 'Ye voucher pehle hi poora ada ho chuka hai.']);
            }
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Amount baqi rakam (' . number_format($balance, 2) . ') se zyada nahi ho sakti.']);
            }

            $payment = DocumentNumber::retry(fn () => FeePayment::create([
                'receipt_no'   => DocumentNumber::receipt(),
                'fee_id'       => $locked->id,
                'amount'       => $amount,
                'payment_date' => $request->input('payment_date'),
                'method'       => $request->input('method'),
                'note'         => $request->input('note') ?: null,
                'received_by'  => $request->user()->id,
            ]));

            $locked->refreshStatus();

            return $payment;
        });

        return redirect()->route('fees.show', $fee)
            ->with('success', "Payment receive ho gayi. Receipt no: {$payment->receipt_no}");
    }

    public function destroy(Fee $fee, FeePayment $payment)
    {
        $no = $payment->receipt_no;

        DB::transaction(function () use ($fee, $payment) {
            $payment->delete();
            $fee->refresh()->refreshStatus();
        });

        return redirect()->route('fees.show', $fee)->with('success', "Payment {$no} cancel kar di gayi.");
    }
}
