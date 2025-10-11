<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public $projects = [];
    public $phases = [];
    public $tasks = [];
    public float $totalBudget = 0;


    public $selectedProjectId = null;
    public bool $showAddBudgetModal = false;
    public ?int $selectedPhaseId = null;

    public float $estimated_cost = 0;
    public float $actual_cost = 0;
    public float $variance = 0;

    public $totalEstimated = 0;
    public $totalActual = 0;
    public $totalVariance = 0;

    public function mount()
    {
        $this->projects = DB::table('projects')->get();
        $this->phases = collect();
        $this->tasks = collect();
    }

    public function updatedSelectedProjectId($projectId)
    {
        $this->phases = DB::table('project_phases')
            ->where('project_id', $projectId)
            ->get();

        $this->tasks = DB::table('tasks')
            ->whereIn('phase_id', $this->phases->pluck('phase_id'))
            ->get();

        $this->calculateBudgetTotals($projectId);
    }

    public function calculateBudgetTotals($projectId)
{
    // ✅ Get total project budget from projects table
    $this->totalBudget = DB::table('projects')
        ->where('project_id', $projectId)
        ->value('budget_total');

    // ✅ Get total estimated cost from budgets table
    $this->totalEstimated = DB::table('budgets')
        ->where('project_id', $projectId)
        ->sum('estimated_cost');

    // ✅ Get total actual cost from resource_allocations
    $this->totalActual = DB::table('resource_allocations as ra')
        ->join('tasks as t', 't.task_id', '=', 'ra.task_id')
        ->join('project_phases as p', 'p.phase_id', '=', 't.phase_id')
        ->where('p.project_id', $projectId)
        ->sum('ra.cost');

    // ✅ Compute variance
    $this->totalVariance = $this->totalEstimated - $this->totalActual;
}

    public function openAddBudgetModal($phaseId)
    {
        $this->selectedPhaseId = $phaseId;
        $this->estimated_cost = 0;
        $this->showAddBudgetModal = true;
    }

    public function getActualCostForPhase($phaseId)
{
    return DB::table('resource_allocations as ra')
        ->join('tasks as t', 't.task_id', '=', 'ra.task_id')
        ->where('t.phase_id', $phaseId)
        ->sum('ra.cost');
}


    public function saveBudget()
{
    $this->validate([
        'estimated_cost' => 'required|numeric|min:0',
    ]);

    DB::transaction(function () {
        // ✅ Use your proven working actual cost logic
        $actualCost = $this->getActualCostForPhase($this->selectedPhaseId);

        // ✅ Compute variance
        $variance = $this->estimated_cost - $actualCost;

        // ✅ Insert budget record
        DB::table('budgets')->insert([
            'project_id' => $this->selectedProjectId,
            'phase_id' => $this->selectedPhaseId,
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $actualCost,
            'variance' => $variance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ✅ Journal entry
        $reference = 'BUD-' . strtoupper(uniqid());
        $phaseName = DB::table('project_phases')->where('phase_id', $this->selectedPhaseId)->value('phase_name');
        $projectName = DB::table('projects')->where('project_id', $this->selectedProjectId)->value('project_name');

        DB::table('journal_entries')->insert([
            'date' => now()->toDateString(),
            'reference_no' => $reference,
            'description' => "Budget added for project '{$projectName}' (Phase: {$phaseName}) — Estimated ₱" .
                             number_format($this->estimated_cost, 2) .
                             " | Actual ₱" . number_format($actualCost, 2),
            'created_by' => auth()->id() ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    // ✅ Refresh totals after saving
    $this->calculateBudgetTotals($this->selectedProjectId);
    $this->showAddBudgetModal = false;
}



};
?>

<!-- ✅ View Section -->
<div class="p-6">

    <!-- Header -->
    <div style="
        background:#2e7d32;
        color:white;
        padding:1rem 1.5rem;
        border-radius:10px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        box-shadow:0 4px 10px rgba(0,0,0,0.15);
    ">
        <h2 style="font-size:1.3rem; font-weight:600; margin:0;">💰 Budget Management</h2>
        @if ($selectedProjectId)
            <span style="font-size:0.9rem;">Project ID: {{ $selectedProjectId }}</span>
        @endif
    </div>

    <!-- Project Dropdown -->
    <div style="margin-top:1.2rem;">
        <label style="font-weight:600; color:#1b5e20;">Select Project</label>
        <select wire:model.live="selectedProjectId" 
            style="width:100%; padding:0.7rem; border:1px solid #ccc; border-radius:8px; margin-top:0.3rem;">
            <option value="">-- Choose Project --</option>
            @foreach ($projects as $proj)
                <option value="{{ $proj->project_id }}">{{ $proj->project_name }}</option>
            @endforeach
        </select>
    </div>

    <!-- Project Budget Summary -->
    @if ($selectedProjectId && $phases->isNotEmpty())
        <div style="
    margin-top:1.8rem;
    background:white;
    border-radius:10px;
    padding:1.2rem 1.5rem;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
    border-left:6px solid #2e7d32;
">
    <h3 style="font-size:1.25rem; font-weight:600; color:#1b5e20;">📊 Project Budget Summary</h3>

    <div style="margin-top:0.8rem; display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
        <div style="background:#f1f8e9; padding:1rem; border-radius:8px; text-align:center;">
            <h4 style="font-size:0.9rem; color:#388e3c;">Total Project Budget</h4>
            <p style="font-size:1.3rem; font-weight:600;">₱{{ number_format($totalBudget, 2) }}</p>
        </div>
    </div>
</div>


        <!-- Phases & Tasks -->
        <h2 style="margin-top:1.5rem; font-size:1.2rem; font-weight:600; color:#1b5e20;">📋 Project Phases</h2>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:1rem;">
            @foreach ($phases as $phase)
                <div style="
                    background:white;
                    border-radius:10px;
                    box-shadow:0 2px 6px rgba(0,0,0,0.05);
                    padding:1.2rem;
                ">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 style="font-size:1.05rem; font-weight:600; color:#2e7d32;">{{ $phase->phase_name }}</h3>
                            <p style="font-size:0.85rem; color:#666;">Status: <b>{{ ucfirst($phase->status) }}</b></p>
                        </div>
                        <button wire:click="openAddBudgetModal({{ $phase->phase_id }})"
                            style="background:#43a047; color:white; border:none; border-radius:8px; padding:0.4rem 0.8rem; cursor:pointer;">
                            + Add Budget
                        </button>
                    </div>

                    @php
                        $phaseTasks = $tasks->where('phase_id', $phase->phase_id);
                    @endphp

                    <div style="margin-top:0.8rem;">
                        <h4 style="font-size:0.9rem; color:#1b5e20;">Tasks:</h4>
                        @if ($phaseTasks->isNotEmpty())
                            <ul style="list-style:disc; margin-left:1rem; color:#333;">
                                @foreach ($phaseTasks as $task)
                                    <li>{{ $task->task_name }} <span style="font-size:0.8rem; color:#777;">({{ ucfirst($task->status) }})</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p style="font-size:0.85rem; color:#777;">No tasks found.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Add Budget Modal -->
    @if ($showAddBudgetModal)
        <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; z-index:1000;">
            <div style="background:white; border-radius:10px; width:380px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
                <div style="background:#2e7d32; color:white; padding:0.8rem 1rem; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:1rem;">Add Budget</h3>
                    <button wire:click="$set('showAddBudgetModal', false)" style="background:none; border:none; color:white; font-size:1.2rem; cursor:pointer;">×</button>
                </div>
                <div style="padding:1rem;">
                    <label style="font-weight:600;">Estimated Cost</label>
                    <input type="number" step="0.01" wire:model="estimated_cost" 
                        style="width:100%; margin-top:0.4rem; padding:0.6rem; border:1px solid #ccc; border-radius:6px;">
                    @error('estimated_cost') <p style="color:red; font-size:0.8rem;">{{ $message }}</p> @enderror

                    <button wire:click="saveBudget" 
                        style="width:100%; margin-top:1rem; padding:0.7rem; background:#43a047; color:white; border:none; border-radius:8px; cursor:pointer;">
                        💾 Save Budget
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
