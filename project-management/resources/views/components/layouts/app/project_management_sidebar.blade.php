<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>TGIF Project Management Module</title>

    

    <style>
        /* Reset */
        * { margin:0; padding:0; box-sizing:border-box; }

        /* Body & Layout */
        body {
            font-family: 'Poppins','Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background: #fffef6;
            color: #1f2b16;
        }

/* Sidebar */
.sidebar {
    width: 260px;
    background: #124116; /* same as header */
    color: white;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    padding: 1rem 0;
    height: calc(100vh - 80px); /* slightly below header */
    position: fixed;
    top: 80px; /* moves sidebar slightly down */
    left: 0;
    overflow-y: auto;
}
        .sidebar h3 {
            padding: 0 1.5rem;
            margin-bottom: 1rem;
            color: #66bb6a;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .sidebar nav ul { list-style:none; }
        .nav-item {
            display:block;
            padding:0.75rem 1.5rem;
            text-decoration:none;
            color:white;
            cursor:pointer;
            transition:0.3s;
        }
        .nav-item:hover { background: rgba(255,179,0,0.15); }
        .nav-item.active { background: rgba(46,125,50,0.5); color:#ffb300; }

  /* Main Wrapper */
.main-wrapper {
    margin-left: 260px; /* leave space for sidebar */
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.main-header {
    width: 100%;
    padding: 1.0rem 2rem;
    background-color: #124116; /* same as sidebar */
    color: white;
    font-weight: 700;
    font-size: 2rem;
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Main Content */
.main-content {
    margin-left: 260px; /* space for sidebar */
    padding: 2rem;
    background: #fffef6;
    min-height: calc(100vh - 80px); /* leave space for header */
}
        /* Cards */
        .dashboard-cards {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
            gap:1rem;
        }
        .card {
            background:#fff;
            padding:1rem;
            border-radius:10px;
            box-shadow:0 8px 24px rgba(0,0,0,0.08);
        }
        .card-title { font-size:1rem; margin-bottom:0.5rem; color:#1b5e20; }
        .card-value { font-size:1.6rem; font-weight:600; color:#2e7d32; }

        /* Table */
        .data-table { margin-top:1.5rem; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
        .table-header { background:#2e7d32; color:#fff; padding:1rem; display:flex; justify-content:space-between; align-items:center; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:0.75rem 1rem; border-bottom:1px solid #eee; text-align:left; }
        th { background:#f8f9fa; color:#2c5530; }

        /* Buttons */
        .btn { padding:0.5rem 1rem; border:none; border-radius:6px; cursor:pointer; }
        .btn-primary { background:#2e7d32; color:#fff; }
        .btn-warning { background:#ffb300; color:#1f2b16; }
        .btn-success { background:#43a047; color:#fff; }
        .btn-danger { background:#e53935; color:#fff; }

        /* Forms */
        .form-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap:0.75rem;
            margin:1rem 0;
        }
        .form-grid input, .form-grid select { padding:0.6rem 0.8rem; border:1px solid #d6e3d3; border-radius:8px; }

        /* Modal */
        .modal { position:fixed; inset:0; background:rgba(0,0,0,0.45); display:none; align-items:center; justify-content:center; padding:1rem; z-index:1000; }
        .modal[aria-hidden="false"] { display:flex; }
        .modal-dialog { background:#fff; border-radius:10px; width:100%; max-width:520px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; padding:1rem; background:#2e7d32; color:#fff; }
        .modal-body { padding:1rem; display:grid; gap:0.5rem; }
        .modal-footer { padding:0.75rem 1rem; background:#f7f7f7; display:flex; justify-content:flex-end; gap:0.5rem; }

        #gantt {
    width: 100%;
    min-height: 400px;
}

.gantt-tooltip {
    background: #fff;
    border: 1px solid #ccc;
    padding: 6px;
    font-size: 13px;
    border-radius: 4px;
}

    </style>
</head>
<body>

<!-- Main Header -->
<header class="main-header">
    <h1>TGIF Project Management</h1>
</header>

<!-- Sidebar -->
<aside class="sidebar">
    <h3>Navigation</h3>
    <nav>
        <ul>
            <li><a href="{{ route('home') }}" class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">Project Planning and Scheduling</a></li>
            <li><a href="{{ route('projects.resources') }}" class="nav-item {{ request()->routeIs('projects.resources') ? 'active' : '' }}">Resource Allocation Management</a></li>
            <li><a href="{{ route('projects.budget') }}" class="nav-item {{ request()->routeIs('projects.budget') ? 'active' : '' }}">Budgeting & Cost Tracking</a></li>
            <li><a href="{{ route('projects.progress') }}" class="nav-item {{ request()->routeIs('projects.progress') ? 'active' : '' }}">Progress Monitoring & Reporting</a></li>
        </ul>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content">
    {{ $slot }}
</main>




</body>
</html>
