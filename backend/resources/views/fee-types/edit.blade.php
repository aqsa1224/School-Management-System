<x-app-layout>
    <x-slot name="header"><h2>Edit Fee Type</h2></x-slot>
    <div class="row"><div class="col-lg-6">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('fee-types.update', $feeType) }}" method="POST">
                @csrf @method('PUT')
                @include('fee-types._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">Update Fee Type</button>
                    <a href="{{ route('fee-types.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
