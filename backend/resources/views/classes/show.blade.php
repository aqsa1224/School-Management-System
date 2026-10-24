<x-app-layout>
    <x-slot name="header">
        <h2>{{ $class->name }}</h2>
    </x-slot>

    <div class="mb-3">
        <a href="{{ route('classes.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Classes</a>
    </div>

    <div class="row g-4">

        {{-- Class info --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Class details</h5>
                    <dl class="mb-0">
                        <dt>Name</dt><dd>{{ $class->name }}</dd>
                        <dt>Sections</dt><dd>{{ $class->sections->count() }}</dd>
                        <dt>Students</dt><dd>{{ $class->students_count }}</dd>
                        <dt>Description</dt><dd class="mb-0">{{ $class->description ?: '-' }}</dd>
                    </dl>
                    <hr>
                    <div class="d-flex gap-2">
                        <a href="{{ route('classes.edit', $class) }}" class="btn btn-outline-gold btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                        <form action="{{ route('classes.destroy', $class) }}" method="POST"
                              onsubmit="return confirm('Delete {{ addslashes($class->name) }}? Is ke sections bhi delete ho jayenge.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sections --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Sections</h5>

                    {{-- Add section --}}
                    <form action="{{ route('classes.sections.store', $class) }}" method="POST" class="row g-2 mb-4">
                        @csrf
                        <div class="col-sm-5">
                            <input type="text" name="name" class="form-control" placeholder="Section name (e.g. A)" maxlength="50" required>
                        </div>
                        <div class="col-sm-4">
                            <input type="number" name="capacity" class="form-control" placeholder="Capacity (optional)" min="1" max="500">
                        </div>
                        <div class="col-sm-3 d-grid">
                            <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Section</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Section</th>
                                    <th>Capacity</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($class->sections as $section)
                                    <tr>
                                        <td style="min-width:120px">
                                            <input type="text" name="name" value="{{ $section->name }}" form="update-{{ $section->id }}"
                                                   class="form-control form-control-sm" maxlength="50" required>
                                        </td>
                                        <td style="min-width:110px">
                                            <input type="number" name="capacity" value="{{ $section->capacity }}" form="update-{{ $section->id }}"
                                                   class="form-control form-control-sm" min="1" max="500" placeholder="-">
                                        </td>
                                        <td class="text-center">{{ $section->students_count }}</td>
                                        <td class="text-end text-nowrap">
                                            <button form="update-{{ $section->id }}" class="btn btn-sm btn-outline-gold" title="Save">
                                                <i class="bi bi-check-lg"></i> Save
                                            </button>
                                            <button form="delete-{{ $section->id }}" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Is class me abhi koi section nahi hai. Upar se add karein.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Forms (table ke bahar, taake forms nested na hon) --}}
                    @foreach ($class->sections as $section)
                        <form id="update-{{ $section->id }}" method="POST"
                              action="{{ route('classes.sections.update', [$class, $section]) }}">
                            @csrf
                            @method('PUT')
                        </form>
                        <form id="delete-{{ $section->id }}" method="POST"
                              action="{{ route('classes.sections.destroy', [$class, $section]) }}"
                              onsubmit="return confirm('Delete section {{ addslashes($section->name) }}?');">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
