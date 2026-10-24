<x-app-layout>
    <x-slot name="header">
        <h2>Edit Class</h2>
    </x-slot>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body p-4">
                    <form action="{{ route('classes.update', $class) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('classes._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">Update Class</button>
                            <a href="{{ route('classes.show', $class) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
