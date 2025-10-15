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


<div class="progress-monitor-container" style="padding:1.5rem; max-width:1100px; margin:auto;">
    <h2 style="font-size:1.4rem; font-weight:bold; color:#1565c0;">📊 Project Progress Monitoring & KPI Dashboard</h2>

    <table style="width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.9rem;">
        <thead style="background:#bbdefb;">
            <tr>
                <th style="padding:8px;">Project</th>
                <th style="padding:8px; text-align:right;">Progress (%)</th>
                <th style="padding:8px; text-align:right;">Budget (₱)</th>
                <th style="padding:8px; text-align:right;">Time Variance (%)</th>
                <th style="padding:8px; text-align:right;">Cost Variance (₱)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projects as $project)
                @php
                    $kpi = collect($kpis)->firstWhere('project_id', $project->project_id);
                    $progress = $taskProgress[$project->project_id] ?? 0;
                @endphp
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:8px;">{{ $project->project_name }}</td>
                    <td style="padding:8px; text-align:right;">{{ number_format($progress, 2) }}%</td>
                    <td style="padding:8px; text-align:right;">{{ number_format($project->budget_total, 2) }}</td>
                    <td style="padding:8px; text-align:right; color:{{ ($kpi->time_variance ?? 0) < 0 ? '#d32f2f' : '#2e7d32' }};">
                        {{ $kpi->time_variance ?? 0 }}%
                    </td>
                    <td style="padding:8px; text-align:right; color:{{ ($kpi->cost_variance ?? 0) > 0 ? '#d32f2f' : '#2e7d32' }};">
                        {{ number_format($kpi->cost_variance ?? 0, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="margin-top:1.5rem; color:#ef6c00;">⚠️ Active Alerts</h3>
    <table style="width:100%; border-collapse:collapse; margin-top:0.5rem; font-size:0.9rem;">
        <thead style="background:#ffe0b2;">
            <tr>
                <th style="padding:8px;">Project</th>
                <th style="padding:8px;">Type</th>
                <th style="padding:8px;">Description</th>
                <th style="padding:8px;">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($alerts as $alert)
                @php
                    $project = collect($projects)->firstWhere('project_id', $alert->project_id);
                @endphp
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:8px;">{{ $project->project_name ?? 'N/A' }}</td>
                    <td style="padding:8px;">{{ $alert->alert_type }}</td>
                    <td style="padding:8px;">{{ $alert->description }}</td>
                    <td style="padding:8px;">{{ \Carbon\Carbon::parse($alert->alert_date)->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="padding:8px; text-align:center;">No active alerts</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
