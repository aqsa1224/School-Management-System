<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\FeePayment;
use App\Support\Pdf;
use App\Support\School;

/** Printable voucher / receipt aur unke PDF */
class FeeDocumentController extends Controller
{
    public function voucher(Fee $fee)
    {
        return view('fees.documents.voucher', $this->voucherData($fee, false));
    }

    public function voucherPdf(Fee $fee)
    {
        if (! Pdf::available()) {
            return $this->noPdf($fee);
        }

        return Pdf::download(
            view('fees.documents.voucher', $this->voucherData($fee, true))->render(),
            "voucher-{$fee->voucher_no}.pdf"
        );
    }

    public function receipt(Fee $fee, FeePayment $payment)
    {
        return view('fees.documents.receipt', $this->receiptData($fee, $payment, false));
    }

    public function receiptPdf(Fee $fee, FeePayment $payment)
    {
        if (! Pdf::available()) {
            return $this->noPdf($fee);
        }

        return Pdf::download(
            view('fees.documents.receipt', $this->receiptData($fee, $payment, true))->render(),
            "receipt-{$payment->receipt_no}.pdf"
        );
    }

    // ---------------- helpers ----------------

    private function voucherData(Fee $fee, bool $pdf): array
    {
        $fee->load(['student.schoolClass', 'student.section', 'feeType', 'payments']);

        return ['fee' => $fee, 'school' => School::forDocument($pdf), 'pdf' => $pdf];
    }

    private function receiptData(Fee $fee, FeePayment $payment, bool $pdf): array
    {
        abort_unless($payment->fee_id === $fee->id, 404);

        $fee->load(['student.schoolClass', 'student.section', 'feeType', 'payments']);
        $payment->load('receivedBy');

        // Is receipt tak kitna wasool hua aur kitna baqi (baad ki payments ginti me nahi)
        $paidUpToHere = (float) $fee->payments
            ->filter(fn ($p) => $p->id <= $payment->id)->sum('amount');

        return [
            'fee' => $fee, 'payment' => $payment, 'school' => School::forDocument($pdf), 'pdf' => $pdf,
            'balanceAfter' => max(0, $fee->net_amount - $paidUpToHere),
        ];
    }

    private function noPdf(Fee $fee)
    {
        return redirect()->route('fees.show', $fee)->with('error',
            'PDF banane wali library install nahi hai. Terminal me ye chalayen: composer require dompdf/dompdf  (phir page dobara kholein). Tab tak "Print" button se bhi PDF save ho sakta hai.');
    }
}
