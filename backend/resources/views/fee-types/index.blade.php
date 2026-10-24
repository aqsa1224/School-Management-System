<x-app-layout>
    <x-slot name="header"><h2>Fee Types</h2></x-slot>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <form method="GET" action="{{ route('fee-types.index') }}" class="d-flex gap-2">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search fee type...">
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                    @if ($search !== '')
                        <a href="{{ route('fee-types.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </form>
                <div class="d-flex gap-2">
                    <a href="{{ route('fees.index') }}" class="btn btn-outline-dark"><i class="bi bi-cash-coin me-1"></i> Fees</a>
                    <a href="{{ route('fee-types.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Fee Type</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>#</th><th>Fee type</th><th class="text-end">Default amount</th><th class="text-center">Vouchers</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($types as $type)
                            <tr>
                                <td>{{ $types->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $type->name }}</td>
                                <td class="text-end">{{ number_format($type->default_amount, 2) }}</td>
                                <td class="text-center">{{ $type->fees_count }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('fee-types.edit', $type) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('fee-types.destroy', $type) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($type->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-tags fs-1 d-block mb-2"></i>
                                @if ($search !== '') "{{ $search }}" naam ki koi fee type nahi mili.
                                @else Abhi koi fee type nahi hai. "Add Fee Type" par click karein. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $types->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</x-app-layout>
