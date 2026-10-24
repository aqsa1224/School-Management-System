<x-app-layout>
    <x-slot name="header"><h2>Edit User</h2></x-slot>
    <div class="row"><div class="col-xl-9">
        <div class="card"><div class="card-body p-4">
            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf @method('PUT')
                @include('users._form')
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary px-4">Update User</button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div></div>
    </div></div>
</x-app-layout>
