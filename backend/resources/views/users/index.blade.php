<x-app-layout>
    <x-slot name="header"><h2>Users</h2></x-slot>

    @php
        $roleBadge = ['admin' => 'text-bg-dark', 'teacher' => 'text-bg-primary', 'accountant' => 'text-bg-success', 'receptionist' => 'text-bg-info'];
        $filtersOn = $search !== '' || $role !== '' || $status !== '';
    @endphp

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                <form method="GET" action="{{ route('users.index') }}" class="d-flex flex-wrap gap-2">
                    <input type="text" name="q" value="{{ $search }}" class="form-control" style="width:230px" placeholder="Name or email...">
                    <select name="role" class="form-select" style="width:150px">
                        <option value="">All roles</option>
                        @foreach (config('roles.labels') as $value => $label)
                            <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select" style="width:140px">
                        <option value="">All status</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                    @if ($filtersOn) <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Clear</a> @endif
                </form>
                <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add User</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="fw-semibold">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id()) <span class="badge text-bg-light border">You</span> @endif
                                    @if ($user->teacher) <div class="text-muted small fw-normal"><i class="bi bi-person-badge"></i> Teacher record linked</div> @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td><span class="badge {{ $roleBadge[$user->role] ?? 'text-bg-secondary' }}">{{ $user->role_label }}</span></td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-gold" title="Edit"><i class="bi bi-pencil"></i></a>
                                    @if ($user->id !== auth()->id())
                                        <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete {{ addslashes($user->name) }}?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-person-gear fs-1 d-block mb-2"></i>
                                @if ($filtersOn) Is search/filter se koi user nahi mila. @else Abhi koi user nahi hai. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</x-app-layout>
