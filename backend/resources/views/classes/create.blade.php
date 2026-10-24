<x-app-layout>
    <x-slot name="header">
        <h2>Add Class</h2>
    </x-slot>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body p-4">
                    <form action="{{ route('classes.store') }}" method="POST">
                        @csrf
                        @include('classes._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">Save Class</button>
                            <a href="{{ route('classes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
