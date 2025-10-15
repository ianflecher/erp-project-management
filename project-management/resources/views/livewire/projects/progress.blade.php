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
        // 1️⃣ Load all active projects
        $this->projects = DB::table('projects')->get()->toArray();

        // 2️⃣ Compute KPIs, progress, and alerts
        foreach ($this->projects as $project) {
            $this->calculateTaskProgress($project); // (a)
            $this->updateKPI($project);             // (c)
            $this->checkAlerts($project);           // (d)
        }

        // 3️⃣ Load data for dashboard
        $this->kpis = DB::table('kpi_monitoring')->get()->toArray();
        $this->alerts = DB::table('alerts')->orderByDesc('alert_date')->limit(10)->get()->toArray();
    }

    /**
     * (a) Compute average task progress per project
     */
    private function calculateTaskProgress($project)
    {
        $tasks = DB::table('tasks')
            ->join('project_phases as p', 'p.phase_id', '=', 'tasks.phase_id')
            ->where('p.project_id', $project->project_id)
            ->select('tasks.progress_percentage')
            ->get();

        $averageProgress = $tasks->avg('progress_percentage') ?? 0;

        // Store in memory (no DB column for completion%)
        $this->taskProgress[$project->project_id] = round($averageProgress, 2);
    }

    /**
     * (c) Compute KPIs for time and cost
     */
    private function updateKPI($project)
    {
        $today = Carbon::today();
        $start = Carbon::parse($project->start_date);
        $end = Carbon::parse($project->end_date);

        // Time variance = actual progress - expected progress
        $duration = max($start->diffInDays($end), 1);
        $daysElapsed = $start->diffInDays($today);
        $expectedProgress = min(($daysElapsed / $duration) * 100, 100);
        $actualProgress = $this->taskProgress[$project->project_id] ?? 0;
        $timeVariance = round($actualProgress - $expectedProgress, 2); // negative = behind

        // Cost variance = Actual - Budget
        $budget = (float) $project->budget_total;
        $actualCost = DB::table('cost_tracking')
            ->join('tasks as t', 't.task_id', '=', 'cost_tracking.task_id')
            ->join('project_phases as p', 'p.phase_id', '=', 't.phase_id')
            ->where('p.project_id', $project->project_id)
            ->sum('cost_tracking.amount');
        $costVariance = round($actualCost - $budget, 2);

        // Insert or update KPI record
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

    /**
     * (d) Trigger alerts for delays and cost overruns
     */
    private function checkAlerts($project)
    {
        $kpi = DB::table('kpi_monitoring')
            ->where('project_id', $project->project_id)
            ->first();

        if (!$kpi) return;

        // Delay alert (behind schedule)
        if ($kpi->time_variance < 0) {
            $this->insertAlert($project->project_id, 'Delay', 'Project is behind schedule by ' . abs($kpi->time_variance) . '%.');
        }

        // Cost overrun alert
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
/* --- Container --- */
.progress-monitor-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 1.5rem 2rem;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    font-family: "Segoe UI", sans-serif;
}

/* --- Headings --- */
.progress-monitor-container h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #166534; /* dark green */
    margin-bottom: 1rem;
}

.progress-monitor-container h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #22c55e; /* bright green */
    margin-top: 2rem;
}

/* --- Table --- */
.progress-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    margin-top: 1rem;
}

.progress-table thead {
    background: #dcfce7; /* light green */
    color: #166534; /* dark green */
}

.progress-table th, 
.progress-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.progress-table th {
    font-weight: 600;
}

/* Right-aligned cells */
.progress-table td.text-right {
    text-align: right;
}

/* KPI Colors */
.kpi-positive {
    color: #22c55e; /* green */
    font-weight: 600;
}

.kpi-negative {
    color: #b91c1c; /* red */
    font-weight: 600;
}

/* Alerts Table */
.alerts-table thead {
    background: #bbf7d0; /* light green alert header */
    color: #166534; /* dark green */
}

.alerts-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
}

/* No data row */
.table-no-data {
    text-align: center;
    color: #6b7280;
    font-style: italic;
    padding: 1rem 0;
}

/* Hover row effect */
.progress-table tbody tr:hover,
.alerts-table tbody tr:hover {
    background: #f0fdf4; /* soft green hover */
}

/* Responsive */
@media (max-width: 768px) {
    .progress-monitor-container {
        padding: 1rem;
    }
    .progress-table, .alerts-table {
        font-size: 0.85rem;
    }
    .progress-table th, .progress-table td,
    .alerts-table th, .alerts-table td {
        padding: 0.5rem;
    }
}


</style>
<div class="progress-monitor-container">
    <h2>📊 Project Progress Monitoring & KPI Dashboard</h2>

    <!-- KPI Table -->
    <table class="progress-table">
        <thead>
            <tr>
                <th>Project</th>
                <th class="text-right">Progress (%)</th>
                <th class="text-right">Budget (₱)</th>
                <th class="text-right">Time Variance (%)</th>
                <th class="text-right">Cost Variance (₱)</th>
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
                    <td class="text-right">{{ number_format($progress, 2) }}%</td>
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
