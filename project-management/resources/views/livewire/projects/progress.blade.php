<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $projects = [];
    public array $kpis = [];
    public array $alerts = [];
    public array $taskProgress = [];

    public function mount()
    {
        $this->projects = DB::table('projects')->get()->toArray();

        foreach ($this->projects as $project) {
            $this->calculateTaskProgress($project);
            $this->updateKPI($project);
            $this->checkAlerts($project);
        }

        $this->kpis = DB::table('kpi_monitoring')->get()->toArray();
        $this->alerts = DB::table('alerts')->orderByDesc('alert_date')->limit(10)->get()->toArray();
    }

    private function calculateTaskProgress($project)
    {
        $tasks = DB::table('tasks')
            ->join('project_phases as p', 'p.phase_id', '=', 'tasks.phase_id')
            ->where('p.project_id', $project->project_id)
            ->select('tasks.progress_percentage')
            ->get();

        $this->taskProgress[$project->project_id] = round($tasks->avg('progress_percentage') ?? 0, 2);
    }

    private function updateKPI($project)
    {
        $today = Carbon::today();
        $start = Carbon::parse($project->start_date);
        $end = Carbon::parse($project->end_date);

        $duration = max($start->diffInDays($end), 1);
        $daysElapsed = $start->diffInDays($today);
        $expectedProgress = min(($daysElapsed / $duration) * 100, 100);
        $actualProgress = $this->taskProgress[$project->project_id] ?? 0;
        $timeVariance = round($actualProgress - $expectedProgress, 2);

        $budget = (float) $project->budget_total;
        $actualCost = DB::table('cost_tracking')
            ->join('tasks as t', 't.task_id', '=', 'cost_tracking.task_id')
            ->join('project_phases as p', 'p.phase_id', '=', 't.phase_id')
            ->where('p.project_id', $project->project_id)
            ->sum('cost_tracking.amount');

        $costVariance = round($actualCost - $budget, 2);

        $exists = DB::table('kpi_monitoring')->where('project_id', $project->project_id)->exists();

        $data = [
            'project_id' => $project->project_id,
            'time_variance' => $timeVariance,
            'cost_variance' => $costVariance,
            'last_updated' => now(),
            'updated_at' => now(),
        ];

        if ($exists) {
            DB::table('kpi_monitoring')->where('project_id', $project->project_id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('kpi_monitoring')->insert($data);
        }
    }

    private function checkAlerts($project)
    {
        $kpi = DB::table('kpi_monitoring')->where('project_id', $project->project_id)->first();
        if (!$kpi) return;

        if ($kpi->time_variance < 0) {
            $this->insertAlert($project->project_id, 'Delay', 'Project is behind schedule by ' . abs($kpi->time_variance) . '%.');
        }

        if ($kpi->cost_variance > 0) {
            $this->insertAlert($project->project_id, 'Cost Overrun', 'Actual cost exceeds budget by ₱' . number_format($kpi->cost_variance, 2));
        }
    }

    private function insertAlert($projectId, $type, $description)
    {
        $exists = DB::table('alerts')
            ->where('project_id', $projectId)
            ->where('alert_type', $type)
            ->where('resolved', 0)
            ->exists();

        if (!$exists) {
            DB::table('alerts')->insert([
                'project_id' => $projectId,
                'alert_type' => $type,
                'description' => $description,
                'alert_date' => now(),
                'resolved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
?>
<style>
/* --- General Container --- */
.dashboard-container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 2.5rem;
    background: #f0fdf4; /* softer green tint */
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    font-family: "Segoe UI", sans-serif;
}

/* --- Headings --- */
.dashboard-container h2 {
    font-size: 2rem;
    font-weight: 700;
    color: #065f46;
    margin-bottom: 2rem;
    text-align: center;
}

.dashboard-container h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #047857;
    margin-top: 3rem;
    margin-bottom: 1rem;
}

/* --- Tables --- */
.dashboard-table, .alerts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    margin-top: 1rem;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    background: #ffffff;
}

.dashboard-table th, .alerts-table th {
    background: linear-gradient(90deg, #16a34a, #22c55e); /* lively green gradient */
    color: #ffffff;
    font-weight: 600;
    padding: 0.8rem 1rem;
    text-align: left;
}

.dashboard-table td, .alerts-table td {
    padding: 0.8rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

/* --- KPI Colors --- */
.kpi-positive {
    color: #16a34a;
    font-weight: 600;
}

.kpi-negative {
    color: #dc2626;
    font-weight: 600;
}

/* --- Progress Bar --- */
.progress-bar-wrapper {
    position: relative;
    background: #d1fae5; /* light green background */
    border-radius: 12px;
    height: 26px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #22c55e, #16a34a);
    border-radius: 12px 0 0 12px;
    width: 0%;
    transition: width 0.6s ease;
}

.progress-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%); /* perfectly center */
    font-size: 0.9rem;
    font-weight: 700;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0,0,0,0.4); /* makes text readable */
}


/* --- Alerts Table Enhancements --- */
.alerts-table thead {
    background: linear-gradient(90deg, #16a34a, #22c55e);
    color: #ffffff;
}

.table-no-data {
    text-align: center;
    color: #065f46;
    font-style: italic;
    padding: 1rem 0;
}

/* Hover effect for rows */
.dashboard-table tbody tr:hover,
.alerts-table tbody tr:hover {
    background: #dcfce7; /* light green hover */
    transition: background 0.3s ease;
}

/* --- Responsive --- */
@media (max-width: 992px) {
    .dashboard-container { padding: 1.8rem; }
    .dashboard-table, .alerts-table { font-size: 0.9rem; }
    .progress-label { font-size: 0.75rem; }
}

@media (max-width: 576px) {
    .dashboard-container { padding: 1.2rem; }
    .dashboard-table, .alerts-table { font-size: 0.85rem; }
    .progress-label { font-size: 0.7rem; }
}

/* --- Column Alignment --- */
.dashboard-table th:first-child { text-align: left; }
.dashboard-table th:nth-child(2) { text-align: left; }
.dashboard-table th:nth-child(n+3) { text-align: right; }

.alerts-table th:first-child, .alerts-table td:first-child { text-align: left; }
.alerts-table th:nth-child(2), .alerts-table td:nth-child(2) { text-align: left; }
.alerts-table th:nth-child(3), .alerts-table td:nth-child(3) { text-align: left; }
.alerts-table th:nth-child(4), .alerts-table td:nth-child(4) { text-align: center; }
</style>


<div class="dashboard-container">
    <h2>📊 Project Progress & KPI Dashboard</h2>

    <!-- KPI Table -->
    <table class="dashboard-table">
        <thead>
            <tr>
                <th>Project</th>
                <th>Progress</th>
                <th>Budget (₱)</th>
                <th>Time Variance</th>
                <th>Cost Variance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projects as $project)
                @php
                    $kpi = collect($kpis)->firstWhere('project_id', $project->project_id);
                    $progress = $taskProgress[$project->project_id] ?? 0;
                @endphp
                <tr>
                    <td>{{ $project->project_name }}</td>
                    <td>
    <div class="progress-bar-wrapper">
        <div class="progress-bar-fill" style="width: {{ $progress }}%;"></div>
        <span class="progress-label">{{ number_format($progress, 2) }}%</span>
    </div>
</td>

                    <td class="text-right">{{ number_format($project->budget_total, 2) }}</td>
                    <td class="text-right {{ ($kpi->time_variance ?? 0) < 0 ? 'kpi-negative' : 'kpi-positive' }}">
                        {{ $kpi->time_variance ?? 0 }}%
                    </td>
                    <td class="text-right {{ ($kpi->cost_variance ?? 0) > 0 ? 'kpi-negative' : 'kpi-positive' }}">
                        {{ number_format($kpi->cost_variance ?? 0, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Alerts Table -->
    <h3>⚠️ Active Alerts</h3>
    <table class="alerts-table">
        <thead>
            <tr>
                <th>Project</th>
                <th>Type</th>
                <th>Description</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($alerts as $alert)
                @php $project = collect($projects)->firstWhere('project_id', $alert->project_id); @endphp
                <tr>
                    <td>{{ $project->project_name ?? 'N/A' }}</td>
                    <td>{{ $alert->alert_type }}</td>
                    <td>{{ $alert->description }}</td>
                    <td>{{ \Carbon\Carbon::parse($alert->alert_date)->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="table-no-data">No active alerts</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
