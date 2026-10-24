<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Voucher {{ $fee->voucher_no }}</title>
    @include('fees.documents._style')
</head>
<body class="{{ $pdf ? 'pdf' : '' }}">
@php
    $cur = $school['currency'];
    $m = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
    $paid = $fee->paid_amount;
    $balance = $fee->balance;
@endphp

@unless ($pdf)
    <div class="toolbar">
        <a href="{{ route('fees.show', $fee) }}">&larr; Back</a>
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('fees.voucher.pdf', $fee) }}">Download PDF</a>
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

    <div class="title">FEE VOUCHER</div>

    <table class="grid">
        <tr>
            <td><span class="muted">Voucher no:</span> <span class="strong">{{ $fee->voucher_no }}</span></td>
            <td class="right"><span class="muted">Issued:</span> {{ $fee->created_at->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><span class="muted">Month:</span> {{ $fee->for_month?->format('F Y') ?? '-' }}</td>
            <td class="right"><span class="muted">Due date:</span> <span class="strong">{{ $fee->due_date?->format('d M Y') ?? '-' }}</span></td>
        </tr>
    </table>

    <table class="grid" style="margin-top:4px">
        <tr><td class="muted" style="width:110px">Student</td><td class="strong">{{ $fee->student->full_name }}</td></tr>
        <tr><td class="muted">Admission no</td><td>{{ $fee->student->admission_no }}</td></tr>
        <tr><td class="muted">Class</td><td>{{ $fee->student->schoolClass->name }}{{ $fee->student->section ? ' - ' . $fee->student->section->name : '' }}</td></tr>
        <tr><td class="muted">Guardian</td><td>{{ $fee->student->guardian_name }}</td></tr>
    </table>

    <table class="grid box" style="margin-top:8px">
        <tr><th>Description</th><th class="right" style="width:130px">Amount</th></tr>
        <tr><td>{{ $fee->feeType->name }}{{ $fee->for_month ? ' - ' . $fee->for_month->format('M Y') : '' }}</td><td class="right">{{ $m($fee->amount) }}</td></tr>
        <tr><td>Discount</td><td class="right">- {{ $m($fee->discount) }}</td></tr>
        <tr><td class="strong">Net payable</td><td class="right strong">{{ $m($fee->net_amount) }}</td></tr>
        <tr><td>Paid so far</td><td class="right">{{ $m($paid) }}</td></tr>
        <tr><td class="strong">Balance due</td><td class="right big">{{ $m($balance) }}</td></tr>
    </table>

    <div class="center" style="margin-top:8px">
        <span class="status {{ $fee->status }}">{{ strtoupper($fee->status) }}</span>
    </div>

    <p class="muted center" style="margin-top:6px;margin-bottom:0">Barah-e-meherbani due date tak fee jama karwa dein. Receipt zaroor hasil karein.</p>

    <table class="grid sign"><tr>
        <td><div class="line">Accountant</div></td>
        <td><div class="line">Guardian</div></td>
    </tr></table>
</div>
</body>
</html>
