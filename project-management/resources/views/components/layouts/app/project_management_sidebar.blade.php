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

/* ===== Friesday Modal Wrapper ===== */
.friesday-modal {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0, 0, 0, 0.4);
    z-index: 1000;
}

/* ===== Modal Box ===== */
.friesday-modal-box {
    width: 100%;
    max-width: 90rem; /* same as Tailwind max-w-7xl */
    background: #fff;
    border-radius: 0.5rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

/* ===== Header ===== */
.friesday-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #15803d; /* green-700 */
    color: #fff;
    padding: 0.75rem 1rem;
}

.friesday-modal-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
}

.friesday-modal-close {
    color: #fff;
    font-size: 1.5rem;
    font-weight: bold;
    background: none;
    border: none;
    cursor: pointer;
    transition: color 0.2s ease;
}

.friesday-modal-close:hover {
    color: #e5e7eb; /* gray-200 */
}

/* ===== Body ===== */
.friesday-modal-body {
    padding: 1rem;
}

.friesday-table-container {
    overflow-x: auto;
}

/* ===== Table ===== */
.friesday-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #d1d5db; /* gray-300 */
    border-radius: 0.375rem;
}

.friesday-table thead {
    background-color: #f3f4f6; /* gray-100 */
}

.friesday-table th,
.friesday-table td {
    border: 1px solid #d1d5db;
    padding: 0.5rem 0.75rem;
}

.friesday-table th {
    text-align: left;
    font-weight: 600;
}

.friesday-table td {
    vertical-align: middle;
}

.friesday-text-right {
    text-align: right;
}

.friesday-text-center {
    text-align: center;
}

.friesday-table tbody tr:hover {
    background-color: #f9fafb; /* hover effect */
    transition: background-color 0.2s;
}

/* ===== Buttons ===== */
.friesday-btn {
    padding: 0.25rem 0.75rem;
    border: none;
    border-radius: 0.375rem;
    color: #fff;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.friesday-btn-warning {
    background-color: #f59e0b;
}

.friesday-btn-warning:hover {
    background-color: #d97706;
}

.friesday-btn-danger {
    background-color: #dc2626;
}

.friesday-btn-danger:hover {
    background-color: #b91c1c;
}

/* ===== Footer ===== */
.friesday-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    background-color: #f3f4f6;
    padding: 0.75rem 1rem;
}

.friesday-modal-footer button {
    background-color: #f59e0b;
    color: #fff;
    padding: 0.25rem 1rem;
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.friesday-modal-footer button:hover {
    background-color: #d97706;
}

/* ===== Empty State ===== */
.friesday-empty {
    text-align: center;
    color: #9ca3af; /* gray-400 */
    font-style: italic;
    padding: 0.5rem;
}

/* --- Dashboard Container --- */
.dashboard {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 2rem;
    background-color: #f5f6fa;
}

/* --- Cards Grid --- */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* --- Individual Card --- */
.card {
    padding: 1.5rem;
    border-radius: 12px;
    color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card h2 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}
.card p {
    font-size: 2rem;
    font-weight: bold;
}

/* --- Card Colors --- */
.card-green { background: linear-gradient(135deg, #4CAF50, #81C784); }
.card-yellow { background: linear-gradient(135deg, #FFB300, #FFD54F); color: #333; }
.card-blue { background: linear-gradient(135deg, #2196F3, #64B5F6); }
.card-purple { background: linear-gradient(135deg, #9C27B0, #BA68C8); }

/* --- Hover Effect --- */
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

/* --- Table Styles --- */
.table-container {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.projects-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
    background-color: #fff;
}
.projects-table th, .projects-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}
.projects-table th {
    background-color: #1976d2;
    color: #fff;
    font-weight: 600;
    position: sticky;
    top: 0;
}
.projects-table tr:hover {
    background-color: #f1f1f1;
}
.projects-table td {
    color: #333;
}

/* --- Responsive --- */
@media (max-width: 768px) {
    .cards {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 480px) {
    .cards {
        grid-template-columns: 1fr;
    }
}


/* 🌟 Main Container */
.member-container {
    max-width: 920px;
    margin: 2.5rem auto;
    background: linear-gradient(135deg, #ffffff 0%, #f8fdfa 100%);
    border-radius: 18px;
    padding: 2.2rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    font-family: "Inter", "Segoe UI", sans-serif;
}

/* Header */
.member-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #e7f5ee;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}
.member-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #166534;
    margin: 0;
}
.member-header button {
    background: #22c55e;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.1s ease;
}
.member-header button:hover {
    background: #16a34a;
    transform: scale(1.05);
}

/* Member List */
.member-list {
    margin-bottom: 2rem;
}
.member-card {
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    border-radius: 14px;
    padding: 1rem 1.2rem;
    margin-bottom: 0.8rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}
.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.member-details {
    flex: 1;
}
.member-name {
    font-weight: 600;
    font-size: 1.05rem;
    color: #1e293b;
}
.member-role {
    display: inline-block;
    margin-top: 0.25rem;
    background: #bbf7d0;
    color: #14532d;
    border-radius: 9999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 500;
}

/* Buttons */
.member-actions {
    display: flex;
    gap: 0.5rem;
}
.remove-btn, .edit-btn {
    border: none;
    border-radius: 6px;
    padding: 0.4rem 0.7rem;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.2s ease;
}
.remove-btn {
    background: #ef4444;
    color: white;
}
.remove-btn:hover {
    background: #dc2626;
}
.edit-btn {
    background: #3b82f6;
    color: white;
}
.edit-btn:hover {
    background: #2563eb;
}

/* Member Form */
.member-form {
    background: #fafafa;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 1.5rem;
}
.member-form h3 {
    margin-top: 0;
    font-size: 1.2rem;
    color: #14532d;
    margin-bottom: 1rem;
}
.form-group {
    margin-bottom: 1rem;
}
.member-form label {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    color: #374151;
    margin-bottom: 0.35rem;
}
input[type="text"], select {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    background: white;
    transition: border 0.2s ease;
}
input:focus, select:focus {
    border-color: #16a34a;
    outline: none;
    box-shadow: 0 0 0 3px rgba(22,163,74,0.15);
}
.add-btn {
    background: #22c55e;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.2rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease;
}
.add-btn:hover {
    background: #15803d;
}

/* Empty state */
.no-members {
    text-align: center;
    color: #94a3b8;
    padding: 2rem 0;
    font-style: italic;
    font-size: 0.95rem;
}
/* --- Container --- */
.phase-container {
    max-width: 950px;
    margin: 2rem auto;
    background: #ffffff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    font-family: "Segoe UI", sans-serif;
}

/* --- Header --- */
.phase-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #22c55e; /* green main */
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
}

.phase-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #166534; /* dark green */
}

.phase-back-btn {
    text-decoration: none;
    background: #166534;
    color: #fff;
    border-radius: 6px;
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
    transition: background 0.2s ease;
}

.phase-back-btn:hover {
    background: #22c55e;
}

/* --- Table --- */
.phase-table-container {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #e5e7eb; /* gray border */
    background: #f9fafb; /* light gray bg */
}

.phase-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

.phase-table thead {
    background: #dcfce7; /* light green */
    color: #166534;
}

.phase-table th, 
.phase-table td {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.phase-table th {
    font-weight: 600;
}

.phase-table tr:hover {
    background: #f0fdf4; /* soft hover green */
}

/* --- Buttons --- */
.phase-btn {
    border: none;
    border-radius: 6px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    cursor: pointer;
    color: #fff;
    transition: background 0.2s ease, transform 0.1s ease;
}

.phase-btn:hover {
    transform: translateY(-1px);
}

/* Button colors */
.phase-btn-green {
    background: #22c55e;
}
.phase-btn-green:hover {
    background: #166534;
}

.phase-btn-yellow {
    background: #facc15;
    color: #1f2937;
}
.phase-btn-yellow:hover {
    background: #eab308;
    color: #fff;
}

.phase-btn-red {
    background: #ef4444;
}
.phase-btn-red:hover {
    background: #b91c1c;
}

/* --- No Data --- */
.phase-no-data {
    text-align: center;
    color: #6b7280;
    padding: 2rem 0;
    font-style: italic;
}

/* === Task Page Unique Styles === */
.task-container {
    max-width: 950px;
    margin: 2rem auto;
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    font-family: "Segoe UI", sans-serif;
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #2563eb;
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
}

.task-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color:#166534;
}

.task-back-btn {
    background: #166534;
    color: #fff;
    border-radius: 6px;
    padding: 0.4rem 0.8rem;
    text-decoration: none;
    font-size: 0.85rem;
    transition: background 0.2s ease;
}
.task-back-btn:hover {
    background: #2563eb;
}

.task-table-container {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
}

.task-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}
.task-table th, .task-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.task-table thead {
    background: #dbeafe;
    color: #1e3a8a;
}
.task-table tr:hover {
    background: #eff6ff;
}

/* Buttons */
.task-btn {
    border: none;
    border-radius: 6px;
    padding: 0.4rem 0.75rem;
    font-size: 0.8rem;
    cursor: pointer;
    color: #fff;
    transition: background 0.2s ease, transform 0.1s ease;
}
.task-btn:hover {
    transform: translateY(-1px);
}
.task-btn-green { background: #22c55e; }
.task-btn-green:hover { background: #166534; }

.task-btn-yellow { background: #facc15; color: #1f2937; }
.task-btn-yellow:hover { background: #eab308; color: #fff; }

.task-btn-red { background: #ef4444; }
.task-btn-red:hover { background: #b91c1c; }

.task-no-data {
    text-align: center;
    color: #6b7280;
    padding: 2rem 0;
    font-style: italic;
}

/* === Task Modal Unique Styles === */
.task-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 50;
    transition: opacity 0.3s ease;
}

.task-modal-dialog {
    background: #ffffff;
    border-radius: 14px;
    width: 420px;
    padding: 1.5rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    animation: modalPop 0.25s ease;
    font-family: "Segoe UI", sans-serif;
}

@keyframes modalPop {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.task-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #22c55e;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.task-modal-header h3 {
    font-size: 1.2rem;
    color: #166534;
    font-weight: 700;
}

.task-modal-header h3 span {
    color: #22c55e;
    font-weight: 600;
}

.task-modal-body {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

.task-modal-error {
    color: #dc2626;
    font-size: 0.9rem;
    font-style: italic;
}

.task-modal-form {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.task-modal-field label {
    font-weight: 600;
    color: #166534;
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.25rem;
}

.task-modal-field input,
.task-modal-field textarea {
    width: 100%;
    padding: 0.55rem 0.7rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #f9fafb;
    font-size: 0.9rem;
    transition: border 0.2s ease, box-shadow 0.2s ease;
}

.task-modal-field input:focus,
.task-modal-field textarea:focus {
    outline: none;
    border-color: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
}

.task-modal-submit {
    margin-top: 0.5rem;
    align-self: flex-end;
    padding: 0.5rem 1rem;
}

/* Optional small screen support */
@media (max-width: 480px) {
    .task-modal-dialog {
        width: 90%;
        padding: 1rem;
    }
}
/* === Container === */
.resources-container {
    max-width: 1100px;
    margin: 2rem auto;
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    font-family: "Segoe UI", sans-serif;
}
.resources-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #22c55e;
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
}
.resources-header h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #166534;
}

/* === Table === */
.resources-table-wrapper {
    overflow-x: auto;
}
.resources-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}
.resources-table th, .resources-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.resources-table thead {
    background: #dcfce7;
    color: #166534;
    font-weight: 600;
}
.resources-table tr:hover {
    background: #f0fdf4;
}
.resources-status {
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}
.resources-status.active { background: #bbf7d0; color: #14532d; }
.resources-status.unavailable { background: #fee2e2; color: #991b1b; }

/* === Buttons === */
.resources-btn {
    border: none;
    border-radius: 6px;
    padding: 0.45rem 0.9rem;
    font-size: 0.85rem;
    cursor: pointer;
    color: #fff;
    transition: all 0.2s ease;
}
.resources-btn:hover { transform: translateY(-1px); }
.resources-btn-green { background: #22c55e; }
.resources-btn-green:hover { background: #15803d; }
.resources-btn-yellow { background: #facc15; color: #1f2937; }
.resources-btn-yellow:hover { background: #eab308; color: #fff; }
.resources-btn-red { background: #ef4444; }
.resources-btn-red:hover { background: #b91c1c; }
.resources-btn-gray { background: #6b7280; }

/* === Modal === */
.resources-modal {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.45);
    display: flex;
    justify-content: center;
    align-items: flex-start; /* move modal to top */
    padding-top: 100px; 
    animation: fadeIn 0.2s ease-in-out;
}
.resources-modal-box {
    background: #fff;
    border-radius: 14px;
    padding: 1.8rem;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    animation: slideUp 0.25s ease-in-out;
}
.resources-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.resources-modal-header h3 {
    color: #166534;
    font-weight: 700;
}
.resources-close-btn {
    background: none;
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    color: #475569;
}
.resources-close-btn:hover { color: #14532d; }

/* === Form === */
.resources-form-grid {
    display: grid;
    gap: 0.8rem;
}
.resources-form-grid label {
    display: flex;
    flex-direction: column;
    font-weight: 500;
    color: #374151;
}
.resources-form-grid input {
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    transition: border-color 0.2s ease;
}
.resources-form-grid input:focus {
    border-color: #22c55e;
    outline: none;
}
.resources-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* === Empty State === */
.resources-empty {
    text-align: center;
    color: #6b7280;
    padding: 2rem 0;
    font-style: italic;
}

/* === Animations === */
@keyframes fadeIn {
    from { opacity: 0; } to { opacity: 1; }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.resources-form-grid select {
    padding: 0.45rem 0.6rem;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background-color: #fff;
    color: #111827;
    transition: border-color 0.2s ease;
}
.resources-form-grid select:focus {
    border-color: #22c55e;
    outline: none;
}

.resources-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.resources-btn-gray {
    background-color: #6c757d;
    color: white;
    border: none;
    border-radius: 5px;
    padding: 8px 14px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.resources-btn-gray:hover {
    background-color: #5a6268;
}

/* Table container */
.budgetresource-table-container {
    margin: 1rem 0;
    font-family: Arial, sans-serif;
}

.budgetresource-table-header {
    font-size: 1.2rem;
    font-weight: 700;
    color: #166534;
    margin-bottom: 0.5rem;
}

.budgetresource-table {
    width: 100%;
    border-collapse: collapse;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.budgetresource-table th, .budgetresource-table td {
    border: 1px solid #d1fae5;
    padding: 0.6rem 0.8rem;
    text-align: left;
}

.budgetresource-table th {
    background-color: #dcfce7;
    color: #14532d;
    font-weight: 600;
}

.budgetresource-table tr:nth-child(even) {
    background-color: #f0fdf4;
}

.budgetresource-alloc-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f0fdf4;
    padding: 0.3rem 0.5rem;
    margin-bottom: 0.3rem;
    border-radius: 5px;
}

.budgetresource-actions {
    display: flex;
    gap: 0.3rem;
}

.budgetresource-actions-cell {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.budgetresource-no-alloc,
.budgetresource-no-tasks {
    font-style: italic;
    color: #6b7280;
    font-size: 0.85rem;
}

/* Buttons */
.budgetresource-btn-success {
    background-color: #16a34a;
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.budgetresource-btn-success:hover {
    background-color: #15803d;
}

.budgetresource-btn-primary {
    background-color: #2563eb;
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.budgetresource-btn-primary:hover {
    background-color: #1d4ed8;
}

.budgetresource-btn-warning {
    background-color: #facc15;
    color: #1e3a8a;
    border: none;
    border-radius: 5px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.budgetresource-btn-warning:hover {
    background-color: #eab308;
}

.budgetresource-btn-danger {
    background-color: #dc2626;
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.budgetresource-btn-danger:hover {
    background-color: #b91c1c;
}

/* Green header for budgetresource tables */
.budgetresource-table th {
    background-color: #2e7d32; /* dark green */
    color: #fff; /* white text */
    text-align: left;
    padding: 8px;
    font-weight: 600;
    font-size: 0.9rem;
}


.budgetresource-table thead tr {
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}


    </style>
</head>
<body>

<!-- Main Header -->
<header class="main-header" style="display:flex; justify-content:center; align-items:center;">
    <a href="{{ route('dashboard') }}" style="color: inherit; text-decoration: none; font-size:2rem;">
        TGIF Project Management
    </a>
</header>



<!-- Sidebar -->
<aside class="sidebar">
    <h3>Navigation</h3>
    <nav>
        <ul>
            <li><a href="{{ route('projects.home') }}" class="nav-item {{ request()->routeIs('home') ? 'active' : '' }}">Project Planning and Scheduling</a></li>
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
