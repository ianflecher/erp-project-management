<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $projects = [];
    public int $totalProjects = 0;
    public int $ongoingProjects = 0;
    public float $totalBudget = 0.0;

    public function mount()
    {
        $this->projects = DB::table('projects')->get()->toArray();

        $this->totalProjects = count($this->projects);
        $this->ongoingProjects = collect($this->projects)
            ->where('status', '!=', 'Completed')
            ->count();

        $this->totalBudget = collect($this->projects)
            ->sum('budget_total');
    }
}
?>

<style>
/* ============================================================
   🎨 DASHBOARD PAGE-SPECIFIC ENHANCEMENTS
   (non-destructive – keeps your existing global CSS)
   ============================================================ */

/* Ensure dashboard fits well inside the main-content */
.dashboard {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    padding: 1rem 0;
    animation: fadeIn 0.4s ease;
}

/* Fade animation for smooth load */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Card container - already exists, just refined for spacing inside main-content */
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.2rem;
}

/* Keep your existing colors but add a consistent shadow + smooth hover */
/* ===== Dashboard Card Refinement ===== */
.card {
    text-align: center;
    padding: 1.5rem;
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    color: #fff; /* default white text for dark cards */
}

/* Individual Card Colors */
.card-green {
    background: linear-gradient(135deg, #2e7d32, #43a047);
    color: #ffffff; /* white looks good on green */
}

.card-blue {
    background: linear-gradient(135deg, #1565c0, #42a5f5);
    color: #ffffff; /* white looks good on blue */
}

.card-purple {
    background: linear-gradient(135deg, #6a1b9a, #ab47bc);
    color: #ffffff; /* white looks good on purple */
}

/* ✅ Fix for yellow card – darker text for contrast */
.card-yellow {
    background: linear-gradient(135deg, #fff176, #fbc02d);
    color: #3e2723; /* dark brown text for legibility */
}

.card h2 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    letter-spacing: 0.5px;
}

.card p {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
}


.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
}

/* Adjust table container spacing + rounded edges */
.table-container {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
}

/* Table header - styled to match green brand theme */
.table-header {
    background: linear-gradient(90deg, #2e7d32, #43a047);
    color: white;
    padding: 1rem 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Table base refinement */
.projects-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    background: #fff;
}

.projects-table th, .projects-table td {
    padding: 0.85rem 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.projects-table th {
    background: #f9faf9;
    color: #2c5530;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.projects-table tr:hover td {
    background: #f1f8e9;
    transition: background 0.2s ease;
}

/* Make badges more vibrant and consistent in spacing */
.status-badge {
    display: inline-block;
    min-width: 90px;
    text-align: center;
    border-radius: 12px;
    padding: 0.35rem 0.6rem;
    font-weight: 600;
    font-size: 0.8rem;
}
.status-pending {
    background: #fff3cd;
    color: #856404;
}
.status-approved {
    background: #d4edda;
    color: #155724;
}

/* Responsive */
@media (max-width: 768px) {
    .cards {
        grid-template-columns: repeat(2, 1fr);
    }
    .projects-table th:nth-child(3),
    .projects-table td:nth-child(3),
    .projects-table th:nth-child(8),
    .projects-table td:nth-child(8) {
        display: none; /* hide long columns on small screens */
    }
}
</style>

<div class="dashboard">
    <!-- Dashboard Cards -->
    <div class="cards">
        <div class="card card-green">
            <h2>Total Projects</h2>
            <p>{{ $totalProjects }}</p>
        </div>
        <div class="card card-yellow">
            <h2>Ongoing Projects</h2>
            <p>{{ $ongoingProjects }}</p>
        </div>
        <div class="card card-blue">
            <h2>Total Budget</h2>
            <p>₱{{ number_format($totalBudget, 2) }}</p>
        </div>
        <div class="card card-purple">
            <h2>Completed Projects</h2>
            <p>{{ $totalProjects - $ongoingProjects }}</p>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>Projects Overview</h3>
        </div>
        <table class="projects-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project Name</th>
                    <th>Description</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>Manager ID</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td>{{ $project->project_id }}</td>
                        <td>{{ $project->project_name }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($project->description, 50) }}</td>
                        <td>{{ $project->start_date }}</td>
                        <td>{{ $project->end_date }}</td>
                        <td>
                            <span class="status-badge {{ strtolower($project->status) === 'completed' ? 'status-approved' : 'status-pending' }}">
                                {{ ucfirst($project->status) }}
                            </span>
                        </td>
                        <td>₱{{ number_format($project->budget_total, 2) }}</td>
                        <td>{{ $project->project_manager_id }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
