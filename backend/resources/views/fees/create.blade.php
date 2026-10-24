<x-app-layout>
    <x-slot name="header"><h2>New Fee Voucher</h2></x-slot>
    <div class="row"><div class="col-xl-9">
        <div class="card"><div class="card-body p-4">
            @if ($feeTypes->isEmpty())
                <div class="alert alert-warning">Pehle <a href="{{ route('fee-types.create') }}">Fee Types</a> me kam az kam ek fee type banayein.</div>
            @endif
            <form action="{{ route('fees.store') }}" method="POST">
                @csrf
                @include('fees._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4">Create Voucher</button>
                    <a href="{{ route('fees.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <a href="{{ route('fees.bulk.create') }}" class="btn btn-link ms-auto">Poori class ke liye ek saath banayein</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
