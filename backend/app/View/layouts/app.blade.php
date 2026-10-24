<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>

<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            School Management System
        </span>

        <span class="text-white">
            Welcome, {{ Auth::user()->name }}
        </span>
    </div>
</nav>

<div class="container mt-4">

    @yield('content')

</div>

</body>
</html>