<x-app-layout>
    <x-slot name="header"><h2>Fees</h2></x-slot>

    @php
        $badge = ['unpaid' => 'text-bg-danger', 'partial' => 'text-bg-warning', 'paid' => 'text-bg-success'];
        $filtersOn = $search !== '' || $status !== '' || $classId !== '' || $typeId !== '' || $month !== '';
    @endphp

    {{-- Totals (filter ke hisaab se) --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total billed</div>
            <div class="fs-4 fw-semibold">{{ number_format($totals['billed'], 2) }}</div>
        </div></div></div>
        <div class="col-sm-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Collected</div>
            <div class="fs-4 fw-semibold text-success">{{ number_format($totals['collected'], 2) }}</div>
        </div></div></div>
        <div class="col-sm-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">Balance (baqi)</div>
            <div class="fs-4 fw-semibold text-danger">{{ number_format($totals['balance'], 2) }}</div>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
                <form method="GET" action="{{ route('fees.index') }}" class="row g-2 flex-grow-1" style="max-width:980px">
                    <div class="col-md-3">
                        <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Student, admission no, voucher no...">
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="class_id" class="form-select">
                            <option value="">All classes</option>
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}" @selected($classId === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="fee_type_id" class="form-select">
                            <option value="">All fee types</option>
                            @foreach ($feeTypes as $t)
                                <option value="{{ $t->id }}" @selected($typeId === (string) $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <input type="month" name="month" value="{{ $month }}" class="form-control" title="Month">
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All status</option>
                            <option value="unpaid" @selected($status === 'unpaid')>Unpaid</option>
                            <option value="partial" @selected($status === 'partial')>Partial</option>
                            <option value="paid" @selected($status === 'paid')>Paid</option>
                            <option value="overdue" @selected($status === 'overdue')>Overdue</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                        <button class="btn btn-dark" type="submit"><i class="bi bi-funnel"></i></button>
                    </div>
                    @if ($filtersOn)
                        <div class="col-12"><a href="{{ route('fees.index') }}" class="btn btn-sm btn-outline-secondary">Clear filters</a></div>
                    @endif
                </form>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('fee-types.index') }}" class="btn btn-outline-dark"><i class="bi bi-tags me-1"></i> Fee Types</a>
                    <a href="{{ route('fees.bulk.create') }}" class="btn btn-outline-gold"><i class="bi bi-people me-1"></i> Bulk (Class)</a>
                    <a href="{{ route('fees.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Voucher</a>
                </div>
            </div>

            <div class="text-muted small mb-2">{{ $fees->total() }} voucher(s) mile</div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Voucher</th><th>Student</th><th>Fee</th>
                            <th class="text-end">Net</th><th class="text-end">Paid</th><th class="text-end">Balance</th>
                            <th>Due</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fees as $fee)
                            @php
                                $net = $fee->net_amount;
                                $paid = (float) ($fee->payments_sum_amount ?? 0);
                                $balance = max(0, $net - $paid);
                                $overdue = $fee->status !== 'paid' && $fee->due_date && $fee->due_date->lt(today());
                            @endphp
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('fees.show', $fee) }}" class="fw-semibold text-decoration-none">{{ $fee->voucher_no }}</a></td>
                                <td>
                                    <a href="{{ route('students.show', $fee->student) }}" class="text-decoration-none">{{ $fee->student->full_name }}</a>
                                    <div class="text-muted small">{{ $fee->student->admission_no }} | {{ $fee->student->schoolClass->name }}{{ $fee->student->section ? ' - ' . $fee->student->section->name : '' }}</div>
                                </td>
                                <td>{{ $fee->feeType->name }}<div class="text-muted small">{{ $fee->for_month?->format('M Y') ?? '-' }}</div></td>
                                <td class="text-end">{{ number_format($net, 2) }}</td>
                                <td class="text-end">{{ number_format($paid, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($balance, 2) }}</td>
                                <td class="text-nowrap">{{ $fee->due_date?->format('d M Y') ?? '-' }}</td>
                                <td class="text-nowrap">
                                    <span class="badge {{ $badge[$fee->status] ?? 'text-bg-secondary' }}">{{ ucfirst($fee->status) }}</span>
                                    @if ($overdue) <span class="badge text-bg-dark">Overdue</span> @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('fees.show', $fee) }}" class="btn btn-sm btn-outline-dark" title="Open / Receive payment"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('fees.voucher', $fee) }}" class="btn btn-sm btn-outline-dark" title="Print voucher" target="_blank"><i class="bi bi-printer"></i></a>
                                    <a href="{{ route('fees.edit', $fee) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('fees.destroy', $fee) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete voucher {{ $fee->voucher_no }}?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                                @if ($filtersOn) Is search/filter se koi voucher nahi mila.
                                @else Abhi koi voucher nahi hai. "New Voucher" ya "Bulk (Class)" se banayein. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $fees->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</x-app-layout>
