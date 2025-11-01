<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <title>TGIF Project Management Module</title>
    <style>
    </style>
</head>
<body>

<!-- Main Content -->
<main class="main-content">
    {{ $slot }}
</main>

</body>
</html>
