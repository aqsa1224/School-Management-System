<x-app-layout>
    <x-slot name="header"><h2>Voucher {{ $fee->voucher_no }}</h2></x-slot>

    @php
        $badge = ['unpaid' => 'text-bg-danger', 'partial' => 'text-bg-warning', 'paid' => 'text-bg-success'];
        $paid = $fee->paid_amount;
        $balance = $fee->balance;
        $overdue = $fee->status !== 'paid' && $fee->due_date && $fee->due_date->lt(today());
        $methods = ['cash' => 'Cash', 'bank' => 'Bank', 'online' => 'Online'];
    @endphp

    <div class="mb-3"><a href="{{ route('fees.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Fees</a></div>

    <div class="row g-4">
        {{-- Voucher details --}}
        <div class="col-lg-5">
            <div class="card"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">Voucher details</h5>
                    <div>
                        <span class="badge {{ $badge[$fee->status] ?? 'text-bg-secondary' }} fs-6">{{ ucfirst($fee->status) }}</span>
                        @if ($overdue) <span class="badge text-bg-dark fs-6">Overdue</span> @endif
                    </div>
                </div>

                <dl class="mb-3">
                    <dt>Student</dt>
                    <dd>
                        <a href="{{ route('students.show', $fee->student) }}" class="text-decoration-none">{{ $fee->student->full_name }}</a>
                        <div class="text-muted small">{{ $fee->student->admission_no }} | {{ $fee->student->schoolClass->name }}{{ $fee->student->section ? ' - ' . $fee->student->section->name : '' }}</div>
                    </dd>
                    <dt>Fee</dt><dd>{{ $fee->feeType->name }}{{ $fee->for_month ? ' - ' . $fee->for_month->format('F Y') : '' }}</dd>
                    <dt>Due date</dt><dd>{{ $fee->due_date?->format('d M Y') ?? '-' }}</dd>
                    <dt>Created</dt><dd class="mb-0">{{ $fee->created_at->format('d M Y') }}</dd>
                </dl>

                <table class="table table-sm mb-3">
                    <tr><td>Amount</td><td class="text-end">{{ number_format($fee->amount, 2) }}</td></tr>
                    <tr><td>Discount</td><td class="text-end">- {{ number_format($fee->discount, 2) }}</td></tr>
                    <tr class="fw-semibold"><td>Net payable</td><td class="text-end">{{ number_format($fee->net_amount, 2) }}</td></tr>
                    <tr><td>Paid so far</td><td class="text-end text-success">{{ number_format($paid, 2) }}</td></tr>
                    <tr class="fw-bold"><td>Balance</td><td class="text-end text-danger">{{ number_format($balance, 2) }}</td></tr>
                </table>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('fees.voucher', $fee) }}" target="_blank" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i>Print voucher</a>
                    <a href="{{ route('fees.voucher.pdf', $fee) }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
                    <a href="{{ route('fees.edit', $fee) }}" class="btn btn-outline-gold btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <form action="{{ route('fees.destroy', $fee) }}" method="POST" onsubmit="return confirm('Delete voucher {{ $fee->voucher_no }}?');">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                    </form>
                </div>
            </div></div>
        </div>

        <div class="col-lg-7">
            {{-- Receive payment --}}
            <div class="card mb-4"><div class="card-body">
                <h5 class="mb-3">Receive payment</h5>

                @if ($balance <= 0)
                    <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> Ye voucher poora ada ho chuka hai.</div>
                @else
                    <form action="{{ route('fees.payments.store', $fee) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label for="amount" class="form-label fw-medium">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $balance }}" id="amount" name="amount"
                                   value="{{ old('amount', $balance) }}" class="form-control @error('amount') is-invalid @enderror" required>
                            <div class="form-text">Baqi: {{ number_format($balance, 2) }} (kam likhein to partial payment)</div>
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="payment_date" class="form-label fw-medium">Date <span class="text-danger">*</span></label>
                            <input type="date" id="payment_date" name="payment_date" max="{{ today()->toDateString() }}"
                                   value="{{ old('payment_date', today()->toDateString()) }}" class="form-control @error('payment_date') is-invalid @enderror" required>
                            @error('payment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="method" class="form-label fw-medium">Method <span class="text-danger">*</span></label>
                            <select id="method" name="method" class="form-select @error('method') is-invalid @enderror" required>
                                @foreach ($methods as $v => $l)
                                    <option value="{{ $v }}" @selected(old('method', 'cash') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                            @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="note" class="form-label fw-medium">Note</label>
                            <input type="text" id="note" name="note" maxlength="255" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror" placeholder="optional">
                            @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary px-4"><i class="bi bi-cash-coin me-1"></i>Receive &amp; generate receipt</button>
                        </div>
                    </form>
                @endif
            </div></div>

            {{-- Payments --}}
            <div class="card"><div class="card-body">
                <h5 class="mb-3">Payments / Receipts</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-dark">
                            <tr><th>Receipt no</th><th>Date</th><th>Method</th><th class="text-end">Amount</th><th>Received by</th><th class="text-end"></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($fee->payments->sortBy('id') as $p)
                                <tr>
                                    <td class="fw-semibold text-nowrap">{{ $p->receipt_no }}</td>
                                    <td class="text-nowrap">{{ $p->payment_date->format('d M Y') }}</td>
                                    <td>{{ ucfirst($p->method) }}</td>
                                    <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                                    <td class="text-muted small">{{ $p->receivedBy?->name ?? '-' }}</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('fees.receipt', [$fee, $p]) }}" target="_blank" class="btn btn-sm btn-outline-dark" title="Print receipt"><i class="bi bi-printer"></i></a>
                                        <a href="{{ route('fees.receipt.pdf', [$fee, $p]) }}" class="btn btn-sm btn-outline-dark" title="Download PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                        <form action="{{ route('fees.payments.destroy', [$fee, $p]) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Receipt {{ $p->receipt_no }} cancel kar dein? Voucher ka balance wapas barh jayega.');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Cancel payment"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">Abhi koi payment nahi hui.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>
</x-app-layout>
