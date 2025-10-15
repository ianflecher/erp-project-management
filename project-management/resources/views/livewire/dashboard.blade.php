<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $projects = [];
    public int $totalProjects = 0;
    public int $ongoingProjects = 0;
    public int $completedProjects = 0;
    public float $totalBudget = 0.0;
    public float $averageProgress = 0.0;
    public int $delayedProjects = 0;

    public function mount()
    {
        // 1️⃣ Load all projects
        $this->projects = DB::table('projects')->get()->toArray();
        $this->totalProjects = count($this->projects);

        // 2️⃣ Compute ongoing & completed
        $this->ongoingProjects = collect($this->projects)->where('status', '!=', 'Completed')->count();
        $this->completedProjects = $this->totalProjects - $this->ongoingProjects;

        // 3️⃣ Compute total budget
        $this->totalBudget = collect($this->projects)->sum('budget_total');

        // 4️⃣ Compute average progress per project
        $progressArray = [];
        $today = Carbon::today();
        $delayed = 0;

        foreach ($this->projects as $project) {
            $tasks = DB::table('tasks')
                ->join('project_phases', 'project_phases.phase_id', '=', 'tasks.phase_id')
                ->where('project_phases.project_id', $project->project_id)
                ->select('tasks.progress_percentage', 'tasks.end_date')
                ->get();

            if ($tasks->count() > 0) {
                $avg = $tasks->avg('progress_percentage');
                $progressArray[] = $avg;

                // Check if project is delayed
                $expectedProgress = min((($today->diffInDays(Carbon::parse($project->start_date)) / max(Carbon::parse($project->start_date)->diffInDays(Carbon::parse($project->end_date)),1)) * 100), 100);
                if ($avg < $expectedProgress && $project->status != 'Completed') {
                    $delayed++;
                }
            } else {
                $progressArray[] = 0;
            }
        }

        $this->averageProgress = $progressArray ? round(array_sum($progressArray)/count($progressArray),2) : 0;
        $this->delayedProjects = $delayed;
    }
};
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

.card-red {
    background: linear-gradient(135deg, #e57373, #f44336);
    color: #ffffff;
}

.card-progress {
    background: linear-gradient(135deg, #43a047, #66bb6a); /* green gradient */
    color: #ffffff; /* white text for contrast */
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

.card-progress {
    background: linear-gradient(135deg, #fb8c00, #ffb74d); /* yellow gradient */
    color: #3e2723; /* dark brown text for contrast */
}

.cards-center {
    display: flex;
    justify-content: center; /* center horizontally */
    gap: 1.2rem;             /* spacing between cards */
    flex-wrap: wrap;          /* wrap on smaller screens */
    margin-top: 1rem;
}


</style>

<div class="dashboard">
    <!-- Top Cards -->
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
            <h2>Completed Projects</h2>
            <p>{{ $completedProjects }}</p>
        </div>
        <div class="card card-purple">
            <h2>Total Budget</h2>
            <p>₱{{ number_format($totalBudget,2) }}</p>
        </div>
    </div>

    <!-- Centered Bottom Cards -->
    <div class="cards cards-center">
        <div class="card card-progress">
            <h2>Average Progress</h2>
            <p>{{ $averageProgress }}%</p>
        </div>
        <div class="card card-red">
            <h2>Delayed Projects</h2>
            <p>{{ $delayedProjects }}</p>
        </div>
    </div>



    <!-- KPI Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>Project Summary</h3>
        </div>
        <table class="projects-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Ongoing Tasks</th>
                    <th>Completed Tasks</th>
                    <th>Progress (%)</th>
                    <th>Status</th>
                    <th>Budget (₱)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    @php
                        $tasks = DB::table('tasks')
                            ->join('project_phases', 'project_phases.phase_id', '=', 'tasks.phase_id')
                            ->where('project_phases.project_id', $project->project_id)
                            ->get();
                        $completedTasks = $tasks->where('status', 'Completed')->count();
                        $ongoingTasks = $tasks->count() - $completedTasks;
                        $progress = $tasks->avg('progress_percentage') ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $project->project_name }}</td>
                        <td>{{ $ongoingTasks }}</td>
                        <td>{{ $completedTasks }}</td>
                        <td>
                            <div style="background:#d9f9d9; border-radius:6px; overflow:hidden; height:14px; width:120px;">
                                <div style="background:#22c55e; width:{{ $progress }}%; height:100%;"></div>
                            </div>
                            <span style="font-size:0.8rem; margin-left:6px;">{{ round($progress,2) }}%</span>
                        </td>
                        <td>
                            <span class="status-badge {{ strtolower($project->status) === 'completed' ? 'status-approved' : 'status-pending' }}">
                                {{ ucfirst($project->status) }}
                            </span>
                        </td>
                        <td>₱{{ number_format($project->budget_total,2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
