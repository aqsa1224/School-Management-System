<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
            School Management Dashboard
        </h2>
    </x-slot>

    <div class="container mt-5">

        <div class="row">

            <!-- Students -->
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-primary shadow">
                    <div class="card-body text-center">
                        <h5>Total Students</h5>
                        <h2>{{ $students }}</h2>
                    </div>
                </div>
            </div>

            <!-- Teachers -->
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-success shadow">
                    <div class="card-body text-center">
                        <h5>Total Teachers</h5>
                        <h2>{{ $teachers }}</h2>
                    </div>
                </div>
            </div>

            <!-- Subjects -->
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-warning shadow">
                    <div class="card-body text-center">
                        <h5>Total Subjects</h5>
                        <h2>{{ $subjects }}</h2>
                    </div>
                </div>
            </div>

        </div>

        <div class="card shadow">
            <div class="card-body">
                <h3>Welcome to School Management System 🎉</h3>
                <p>
                    Use the navigation menu to manage Students, Teachers, and Subjects.
                </p>
            </div>
        </div>

    </div>

</x-app-layout>