<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <title>TGIF Project Management Module</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
        }

        body {
            display: flex;
            height: 100vh;
            background-color: #ffffff; /* White background */
            color: #000000; /* Default text black */
        }

        aside {
            width: 16rem;
            background-color: #124116; /* Sidebar stays dark green */
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.5rem;
            height: 100vh;
        }

        main {
            flex: 1;
            padding: 2rem;
            background-color: #ffffff; /* White background for main */
            box-sizing: border-box;
            height: 100vh;
            overflow-y: auto;
            color: #000000; /* Black text for main content */
        }
    </style>
</head>
<body class="flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-[#124116] text-white flex flex-col justify-between p-6 shadow-lg">
        <!-- Navigation -->
        <div>
            <a href="{{ route('managebudget') }}"
               class="w-full py-2 px-4 bg-green-700 hover:bg-green-600 rounded font-semibold text-white transition block mb-3">
               Budget 
            </a>
        </div>

        <!-- Logout -->
        <div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full py-2 px-4 bg-red-600 hover:bg-red-500 rounded font-semibold text-white transition">
                    Logout
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 relative">
        <!-- Header -->
        <header class="mb-6 sticky top-0 bg-white z-10 p-2">
            <h1 class="text-3xl font-bold text-black">
                Welcome, {{ Auth::user()->name }}
            </h1>

        </header>

        <div class="mt-4 text-black">
            {{ $slot }}
        </div>
    </main>

</body>
</html>
