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
    public array $budgets = [];
    public ?int $selectedProjectId = null;
    public ?int $projectId = null;


    public bool $showAddBudgetModal = false;
    public ?int $selectedPhaseId = null;
    public bool $showBudgetModal = false;

   public ?float $estimatedCost = null;
    public ?float $actualCost = null;
    public ?float $variance = null;


    public $totalEstimated = 0;
    public $totalActual = 0;
    public $totalVariance = 0;

    public ?int $selectedTaskId = null;

    public function mount()
{
    
    $this->projects = DB::table('projects')->get();
    $this->phases = collect();
    $this->tasks = collect();

    $this->phases = DB::table('project_phases')
        ->where('project_id', $this->projectId)
        ->get();

    $this->tasks = DB::table('tasks')
        ->whereIn('phase_id', $this->phases->pluck('phase_id'))
        ->get();

    $this->budgets = DB::table('budgets')->get()->toArray();

}

    protected function resetBudgetFields(): void
{
    $this->estimatedCost = null;
$this->actualCost = null;
$this->variance = null;

}



    public function openBudgetModal(int $phaseId, int $taskId): void
{
    $this->selectedPhaseId = $phaseId;
    $this->selectedTaskId = $taskId; // ✅ task-level
    $this->resetBudgetFields();
    $this->showBudgetModal = true;
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

    public function openAddBudgetModal(int $phaseId,): void
{
    $this->selectedPhaseId = $phaseId;
    $this->selectedTaskId = null; // ✅ phase-level only
    $this->resetBudgetFields();
    $this->showBudgetModal = true;
}

    public function getActualCostForPhase(int $phaseId): float
{
    return (float) DB::table('resource_allocations as ra')
        ->join('tasks as t', 't.task_id', '=', 'ra.task_id')
        ->where('t.phase_id', $phaseId)
        ->sum('ra.cost');
}


    public function getActualCostForTask(int $taskId): float
{
    return (float) DB::table('resource_allocations')
        ->where('task_id', $taskId)
        ->sum('cost');
}




    public function saveBudget()
{
    $this->validate([
        'estimatedCost' => 'required|numeric|min:0',
    ]);

    DB::transaction(function () {
        // ✅ 1. Compute actual cost (use task-based if applicable)
        $actualCost = $this->selectedTaskId
    ? $this->getActualCostForTask($this->selectedTaskId)   // task-level
    : $this->getActualCostForPhase($this->selectedPhaseId); // phase-level


        // ✅ 2. Compute variance
        $variance = (float)$this->estimatedCost - (float)$actualCost;

        // ✅ 3. Insert or update the budget record (avoid duplicates)
        DB::table('budgets')->updateOrInsert(
    [
        'project_id' => $this->selectedProjectId,
        'phase_id'   => $this->selectedPhaseId,
        'task_id'    => $this->selectedTaskId,
    ],
    [
        'estimated_cost' => (float)$this->estimatedCost,
        'actual_cost'    => (float)$actualCost,
        'variance'       => $variance,
        'updated_at'     => now(),
    ]
);


        // ✅ 4. Create journal entry
        $reference = 'BUD-' . strtoupper(uniqid());

        $phaseName = DB::table('project_phases')
            ->where('phase_id', $this->selectedPhaseId)
            ->value('phase_name');

        $projectName = DB::table('projects')
            ->where('project_id', $this->selectedProjectId)
            ->value('project_name');

        $taskName = $this->selectedTaskId
            ? DB::table('tasks')->where('task_id', $this->selectedTaskId)->value('task_name')
            : null;

        $description = "Budget added for project '{$projectName}'"
            . " (Phase: {$phaseName}"
            . ($taskName ? ", Task: {$taskName}" : "")
            . ") — Estimated ₱" . number_format($this->estimatedCost, 2)
            . " | Actual ₱" . number_format($actualCost, 2);

        DB::table('journal_entries')->insert([
            'date'         => now()->toDateString(),
            'reference_no' => $reference,
            'description'  => $description,
            'created_by'   => auth()->id() ?? 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    });

    // ✅ 5. Refresh budget totals & close modal
    $this->calculateBudgetTotals($this->selectedProjectId);

    $this->budgets = DB::table('budgets')->get()->toArray();
    $this->showAddBudgetModal = false;
    $this->showBudgetModal = false;
    session()->flash('success', 'Budget saved successfully!');
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

                {{-- ✅ Phase-level Budget Display --}}
                @php
                    $phaseBudget = collect($budgets)->firstWhere('phase_id', $phase->phase_id);
                @endphp

                @if($phaseBudget)
                    <p style="margin:0; font-size:0.85rem; color:#388e3c;">
                        💰 Phase Budget — Est: ₱{{ number_format((float)$phaseBudget->estimated_cost, 2) }}
                        | Act: ₱{{ number_format((float)$phaseBudget->actual_cost, 2) }}
                    </p>
                @else
                    <p style="margin:0; font-size:0.85rem; color:#777;">No phase budget set</p>
                @endif
            </div>

            <button wire:click="openAddBudgetModal({{ $phase->phase_id }})"
                style="background:#43a047; color:white; border:none; border-radius:8px; padding:0.4rem 0.8rem; cursor:pointer;">
                + Add Budget
            </button>
        </div>

        <div style="margin-top:0.8rem;">
            <h4 style="font-size:0.9rem; color:#1b5e20;">Tasks:</h4>
            @php
                $phaseTasks = $tasks->where('phase_id', $phase->phase_id);
            @endphp

            @if ($phaseTasks->isNotEmpty())
                <ul style="list-style:none; margin:0; padding:0;">
                    @foreach ($phaseTasks as $task)
                        @php
                            $budgetsCollection = collect($budgets);

                            // Task-level budget first
                            $taskBudget = $budgetsCollection->firstWhere('task_id', $task->task_id);

                            // Fallback to phase budget if no task budget
                            if (!$taskBudget) {
                                $taskBudget = $budgetsCollection->firstWhere('phase_id', $phase->phase_id);
                                $budgetSource = $taskBudget ? 'phase' : null;
                            } else {
                                $budgetSource = 'task';
                            }
                        @endphp

                        <li style="background:#f9f9f9; border-radius:8px; padding:0.6rem 0.8rem; margin-bottom:0.4rem; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>{{ $task->task_name }}</strong>
                                <span style="font-size:0.8rem; color:#777;">({{ ucfirst($task->status) }})</span>

                                @if ($taskBudget)
                                    <p style="margin:0; font-size:0.85rem; color:#388e3c;">
                                        💰 Est: ₱{{ number_format((float)$taskBudget->estimated_cost, 2) }}
                                        | Act: ₱{{ number_format((float)$taskBudget->actual_cost, 2) }}
                                        @if($budgetSource === 'phase')
                                            <span style="font-size:0.75rem; color:#666;">(phase budget)</span>
                                        @else
                                            <span style="font-size:0.75rem; color:#666;">(task budget)</span>
                                        @endif
                                    </p>
                                @else
                                    <p style="margin:0; font-size:0.85rem; color:#777;">No budget set</p>
                                @endif
                            </div>
                        </li>
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

    @if ($showBudgetModal)
    <div class="modal" aria-hidden="false">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>{{ $selectedTaskId ? 'Task' : 'Phase' }} Budget</h3>
            <button 
                type="button" 
                wire:click="$set('showBudgetModal', false)" 
                style="background:none; border:none; color:white; font-size:1.2rem; cursor:pointer;">
                ×
            </button>
        </div>

        <div class="modal-body">
            <form wire:submit.prevent="saveBudget">
                <label for="estimatedCost">Estimated Cost</label>
                <input 
                    id="estimatedCost"
                    type="number"
                    wire:model="estimatedCost"
                    step="0.01"
                    style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:6px;"
                >

                <div class="modal-footer" style="margin-top:1rem;">
                    <button 
                        type="button" 
                        wire:click="$set('showBudgetModal', false)" 
                        style="background:#999; color:white; padding:0.4rem 0.8rem; border:none; border-radius:8px; cursor:pointer;">
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        style="background:#43a047; color:white; padding:0.4rem 0.8rem; border:none; border-radius:8px; cursor:pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
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
