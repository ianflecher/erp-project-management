<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public ?int $selectedProjectId = null; // filled via route
    public array $phases = [];
    public array $tasks = [];

    public float $totalBudget = 0;
    public float $totalEstimated = 0;
    public float $totalActual = 0;
    public float $totalVariance = 0;

    public array $reportData = [];

    public function mount()
    {
        if (!$this->selectedProjectId) {
            abort(404, 'Project ID not provided.');
        }

        // Load all project phases
        $this->phases = DB::table('project_phases')
            ->where('project_id', $this->selectedProjectId)
            ->get()
            ->toArray();

        // Load all tasks under those phases
        $this->tasks = DB::table('tasks')
            ->whereIn('phase_id', array_column($this->phases, 'phase_id'))
            ->get()
            ->toArray();

        // Compute totals and load report
        $this->calculateBudgetTotals();
        $this->loadBudgetReport();
    }

    public function calculateBudgetTotals()
    {
        // Project-level total budget
        $this->totalBudget = DB::table('projects')
            ->where('project_id', $this->selectedProjectId)
            ->value('budget_total') ?? 0;

        // Total estimated from budgets table
        $this->totalEstimated = DB::table('budgets')
            ->where('project_id', $this->selectedProjectId)
            ->sum('estimated_cost');

        // Actual costs now come from cost_tracking (linked to journal_entries)
        $this->totalActual = DB::table('cost_tracking as c')
            ->join('tasks as t', 'c.task_id', '=', 't.task_id')
            ->join('project_phases as p', 't.phase_id', '=', 'p.phase_id')
            ->where('p.project_id', $this->selectedProjectId)
            ->sum('c.amount');

        // Variance = estimated - actual
        $this->totalVariance = $this->totalEstimated - $this->totalActual;
    }

    public function loadBudgetReport()
    {
        $tasks = DB::table('tasks as t')
            ->join('project_phases as p', 't.phase_id', '=', 'p.phase_id')
            ->leftJoin('budgets as b', function ($join) {
                $join->on('b.task_id', '=', 't.task_id')
                     ->where('b.project_id', '=', $this->selectedProjectId);
            })
            ->leftJoin('cost_tracking as c', 'c.task_id', '=', 't.task_id')
            ->leftJoin('journal_entries as j', 'c.finance_reference_no', '=', 'j.reference_no')
            ->where('p.project_id', $this->selectedProjectId)
            ->select(
                't.task_id',
                't.task_name',
                'p.phase_name',
                DB::raw('COALESCE(b.estimated_cost, 0) as estimated_cost'),
                DB::raw('COALESCE(SUM(c.amount), 0) as actual_cost'),
                DB::raw('COALESCE(b.estimated_cost, 0) - COALESCE(SUM(c.amount), 0) as variance'),
                DB::raw('GROUP_CONCAT(DISTINCT j.reference_no SEPARATOR ", ") as journal_refs')
            )
            ->groupBy('t.task_id', 't.task_name', 'p.phase_name', 'b.estimated_cost')
            ->get();

        $this->reportData = $tasks->map(fn ($task) => (object) [
            'phase_name' => $task->phase_name,
            'task_name'  => $task->task_name,
            'estimated'  => $task->estimated_cost ?? 0,
            'actual'     => $task->actual_cost ?? 0,
            'variance'   => $task->variance ?? 0,
            'journals'   => $task->journal_refs ?? '',
        ])->toArray();
    }
};
?>
<div class="task-costs-container" style="padding:1.5rem; max-width:900px; margin:auto;">
    <h2 class="task-title" style="font-size:1.5rem; font-weight:bold; color:#2e7d32;">💰 Budget vs Actual Report</h2>

    <div class="cost-card" style="background:#f4f6f8; border-radius:10px; padding:1rem; margin-top:1rem;">
        <div class="total-task-cost" style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
            <span>Total Budget:</span>
            <span class="total-value">₱{{ number_format($totalBudget, 2) }}</span>
        </div>
        <div class="total-task-cost" style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
            <span>Total Estimated:</span>
            <span class="total-value">₱{{ number_format($totalEstimated, 2) }}</span>
        </div>
        <div class="total-task-cost" style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
            <span>Total Actual:</span>
            <span class="total-value">₱{{ number_format($totalActual, 2) }}</span>
        </div>
        <div class="total-task-cost" style="display:flex; justify-content:space-between;">
            <span>Total Variance:</span>
            <span class="total-value">₱{{ number_format($totalVariance, 2) }}</span>
        </div>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-top:1.5rem; font-size:0.9rem;">
        <thead style="background:#c8e6c9;">
            <tr>
                <th style="padding:8px; text-align:left;">Phase</th>
                <th style="padding:8px; text-align:left;">Task</th>
                <th style="padding:8px; text-align:right;">Estimated (₱)</th>
                <th style="padding:8px; text-align:right;">Actual (₱)</th>
                <th style="padding:8px; text-align:right;">Variance (₱)</th>
                <th style="padding:8px; text-align:left;">Finance Journals</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportData as $row)
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:8px;">{{ $row->phase_name }}</td>
                    <td style="padding:8px;">{{ $row->task_name }}</td>
                    <td style="padding:8px; text-align:right;">{{ number_format($row->estimated, 2) }}</td>
                    <td style="padding:8px; text-align:right;">{{ number_format($row->actual, 2) }}</td>
                    <td style="padding:8px; text-align:right; color:{{ $row->variance < 0 ? '#d32f2f' : '#2e7d32' }};">
                        {{ number_format($row->variance, 2) }}
                    </td>
                    <td style="padding:8px;">{{ $row->journals ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:1rem;">
        <a href="{{ url()->previous() }}" class="back-link" style="color:#2e7d32; text-decoration:none;">← Back</a>
    </div>
</div>
