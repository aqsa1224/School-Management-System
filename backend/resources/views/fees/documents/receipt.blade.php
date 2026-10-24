<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_no }}</title>
    @include('fees.documents._style')
</head>
<body class="{{ $pdf ? 'pdf' : '' }}">
@php
    $cur = $school['currency'];
    $m = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
@endphp

@unless ($pdf)
    <div class="toolbar">
        <a href="{{ route('fees.show', $fee) }}">&larr; Back</a>
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('fees.receipt.pdf', [$fee, $payment]) }}">Download PDF</a>
    </div>
@endunless

<div class="sheet">
    <div class="head">
        @if (! empty($school['logo_src']))
            <div style="margin-bottom:4px"><img src="{{ $school['logo_src'] }}" alt="" style="max-height:44px;max-width:140px"></div>
        @endif
        <div class="school">{{ $school['name'] }}</div>
        @if ($school['address'] || $school['phone'])
            <div class="sub">{{ $school['address'] }}{{ $school['address'] && $school['phone'] ? ' | ' : '' }}{{ $school['phone'] }}</div>
        @endif
    </div>

    <div class="title">FEE RECEIPT</div>

    <table class="grid">
        <tr>
            <td><span class="muted">Receipt no:</span> <span class="strong">{{ $payment->receipt_no }}</span></td>
            <td class="right"><span class="muted">Date:</span> {{ $payment->payment_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><span class="muted">Voucher no:</span> {{ $fee->voucher_no }}</td>
            <td class="right"><span class="muted">Method:</span> {{ ucfirst($payment->method) }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-top:4px">
        <tr><td class="muted" style="width:110px">Received from</td><td class="strong">{{ $fee->student->guardian_name }}</td></tr>
        <tr><td class="muted">Student</td><td>{{ $fee->student->full_name }} ({{ $fee->student->admission_no }})</td></tr>
        <tr><td class="muted">Class</td><td>{{ $fee->student->schoolClass->name }}{{ $fee->student->section ? ' - ' . $fee->student->section->name : '' }}</td></tr>
        <tr><td class="muted">Against</td><td>{{ $fee->feeType->name }}{{ $fee->for_month ? ' - ' . $fee->for_month->format('F Y') : '' }}</td></tr>
        @if ($payment->note)
            <tr><td class="muted">Note</td><td>{{ $payment->note }}</td></tr>
        @endif
    </table>

    <table class="grid box" style="margin-top:8px">
        <tr><th>Details</th><th class="right" style="width:130px">Amount</th></tr>
        <tr><td>Net payable on voucher</td><td class="right">{{ $m($fee->net_amount) }}</td></tr>
        <tr><td class="strong">Amount received</td><td class="right big">{{ $m($payment->amount) }}</td></tr>
        <tr><td>Balance after this receipt</td><td class="right">{{ $m($balanceAfter) }}</td></tr>
    </table>

    <div class="center" style="margin-top:8px">
        <span class="status {{ $balanceAfter <= 0 ? 'paid' : 'partial' }}">{{ $balanceAfter <= 0 ? 'PAID IN FULL' : 'PARTIALLY PAID' }}</span>
    </div>

    <p class="muted center" style="margin-top:6px;margin-bottom:0">Received by: {{ $payment->receivedBy?->name ?? '-' }}</p>

    <table class="grid sign"><tr>
        <td><div class="line">Accountant</div></td>
        <td><div class="line">Stamp</div></td>
    </tr></table>
</div>
</body>
</html>
