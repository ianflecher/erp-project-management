<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    // Projects, Phases & Tasks
    public array $projects = [];
    public array $tasks = [];
    public array $projectTasks = [];
    public array $phases = [];
    public bool $showBudgetModal = false;
    public bool $showAddBudgetModal = false;
    public bool $showAddResourceModal = false;

    public ?int $newBudgetProjectId = null;
    public ?int $newBudgetPhaseId = null;
    public float $newEstimatedCost = 0;

    public string $newResourceName = '';
    public string $newResourceType = 'Food';
    public float $newResourceUnitCost = 0;
    public float $newResourceQuantity = 0;

    // Employees & Resources
    public array $employees = [];
    public array $resources = [];
    public array $budgets = [];

    // Allocation Modal
    public bool $showAllocationModal = false;
    public ?int $selectedTaskId = null;
    public ?int $selectedEmployeeId = null;
    public ?int $selectedResourceId = null;
    public float $allocatedQuantity = 0;
    public float $allocationCost = 0;
    public string $allocationType = 'Employee';
    public ?string $errorMessage = null;

    public function mount()
    {
        $this->loadProjects();
        $this->loadEmployees();
        $this->loadResources();
        $this->loadTasksForAllProjects();
        $this->loadBudgets();
    }

    public function getActualCostForPhase($phaseId)
{
    return DB::table('resource_allocations as ra')
        ->join('tasks as t', 't.task_id', '=', 'ra.task_id')
        ->where('t.phase_id', $phaseId)
        ->sum('ra.cost');
}


    public function openAllocationModal($taskId)
{
    $this->selectedTaskId = $taskId;
    $this->allocatedQuantity = 0;
    $this->selectedResourceId = null;
    $this->showAllocationModal = true;
}

public function closeAllocationModal()
{
    $this->showAllocationModal = false;
    $this->selectedTaskId = null;
    $this->selectedResourceId = null;
    $this->allocatedQuantity = 0;
}

public function saveAllocation()
{
    // 1. Insert allocation
    DB::table('resource_allocations')->insert([
        'task_id' => $this->selectedTaskId,
        'resource_id' => $this->selectedResourceId,
        'allocated_quantity' => $this->allocatedQuantity,
        'allocation_date' => now(),
        'cost' => $this->allocatedQuantity * DB::table('resources')->where('resource_id', $this->selectedResourceId)->value('unit_cost')
    ]);

    // 2. Update available quantity
    DB::table('resources')
        ->where('resource_id', $this->selectedResourceId)
        ->decrement('availability_quantity', $this->allocatedQuantity);

    // 3. Reload resources if needed
    $this->loadResources();

    // Reset modal fields
    $this->selectedTaskId = null;
    $this->selectedResourceId = null;
    $this->allocatedQuantity = 0;
    $this->showAllocationModal = false;
}




    public function loadPhasesForProject($projectId)
{
    $projectId = (int) $projectId; // ensure integer
    if ($projectId > 0) {
        $this->phases = DB::table('project_phases')
            ->where('project_id', $projectId)
            ->select('phase_id', 'phase_name')
            ->get()
            ->toArray();
    } else {
        $this->phases = [];
    }

    $this->newBudgetPhaseId = null; // reset selection
}


    // Automatically called when newBudgetProjectId changes
public function updatedNewBudgetProjectId($projectId)
{
    if ($projectId) {
        $this->phases = DB::table('project_phases')
            ->where('project_id', $projectId)
            ->select('phase_id', 'phase_name')
            ->get()
            ->toArray();
    } else {
        $this->phases = []; // reset if no project selected
    }

    $this->newBudgetPhaseId = null; // reset phase selection
}


    public function openAddBudgetModal()
    {
        $this->showAddBudgetModal = true;
    }

    public function openAddResourceModal()
    {
        $this->showAddResourceModal = true;
    }

    public function saveNewBudget()
    {
        if($this->newBudgetProjectId && $this->newBudgetPhaseId) {
            DB::table('budgets')->insert([
                'project_id' => $this->newBudgetProjectId,
                'phase_id' => $this->newBudgetPhaseId,
                'estimated_cost' => $this->newEstimatedCost,
                'actual_cost' => 0,
                'variance' => $this->newEstimatedCost
            ]);
            $this->loadBudgets();
            $this->showAddBudgetModal = false;
        }
    }

    public function saveNewResource()
    {
        if($this->newResourceName) {
            DB::table('resources')->insert([
                'resource_name' => $this->newResourceName,
                'type' => $this->newResourceType,
                'unit_cost' => $this->newResourceUnitCost,
                'availability_quantity' => $this->newResourceQuantity,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->loadResources();
            $this->showAddResourceModal = false;
        }
    }

    public function openBudgetModal()
    {
        $this->showBudgetModal = true;
        $this->loadResources();
        $this->loadBudgets();
    }

    public function closeBudgetModal()
    {
        $this->showBudgetModal = false;
    }

    public function loadBudgets()
    {
        $this->budgets = DB::table('budgets')->get()->toArray();
    }

    public function loadProjects()
    {
        $this->projects = DB::table('projects')->orderBy('start_date')->get()->toArray();
    }

    public function loadTasks($projectId)
    {
        $this->tasks = DB::table('tasks as t')
            ->join('project_phases as p', 't.phase_id', '=', 'p.phase_id')
            ->where('p.project_id', $projectId)
            ->select('t.*', 'p.phase_name')
            ->get()
            ->toArray();
    }

    public function loadTasksForAllProjects()
    {
        $this->projectTasks = [];
        foreach ($this->projects as $project) {
            $tasks = DB::table('tasks as t')
                ->join('project_phases as p', 't.phase_id', '=', 'p.phase_id')
                ->where('p.project_id', $project->project_id)
                ->select('t.*', 'p.phase_name')
                ->get()
                ->toArray();
            $this->projectTasks[$project->project_id] = $tasks;
        }
    }

    public function loadEmployees()
    {
        $this->employees = DB::table('hr_employees')->get()->toArray();
    }

    public function loadResources()
    {
        $this->resources = DB::table('resources')->get()->toArray();
    }
};
?>

<div>
    <!-- Dashboard Cards -->
    <div class="dashboard-cards">
        <div class="card">
            <div class="card-title">Total Projects</div>
            <div class="card-value">{{ count($projects) }}</div>
        </div>
        <div class="card">
    <div class="card-title">Total Budget</div>
    <div class="card-value">
        {{ number_format(array_sum(array_map(fn($b) => $b->estimated_cost, $budgets ?? [])), 2) }}
    </div>
</div>

        <div class="card">
            <div class="card-title">Total Employees</div>
            <div class="card-value">{{ count($employees) }}</div>
        </div>
        <div class="card">
            <div class="card-title">Types of Resources</div>
            <div class="card-value">{{ count($resources) }}</div>
        </div>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin:1rem 0;">
        <button class="btn btn-success" wire:click="openBudgetModal">View Budget & Resources</button>
    </div>

    <!-- Projects Table -->
    <div class="data-table">
        <div class="table-header">Projects & Tasks</div>
        <table>
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Phase</th>
                    <th>Task</th>
                    <th>Assigned Employee</th>
                    <th>Resources</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($projects as $p)
                    @foreach($projectTasks[$p->project_id] ?? [] as $t)
                        <tr>
                            <td>{{ $p->project_name }}</td>
                            <td>{{ $t->phase_name }}</td>
                            <td>{{ $t->task_name }}</td>
                            <td>
                                @php
                                    $emp = collect($employees)->first(fn($e)=>$e->employee_id==$t->assigned_to);
                                @endphp
                                {{ $emp->full_name ?? 'Unassigned' }}
                            </td>
                            <td>
    @php
        $allocs = DB::table('resource_allocations')
            ->join('resources', 'resources.resource_id', '=', 'resource_allocations.resource_id')
            ->where('resource_allocations.task_id', $t->task_id)
            ->select('resources.resource_name', 'resources.type', 'resource_allocations.allocated_quantity')
            ->get();
    @endphp

    @foreach($allocs as $a)
        @php
            // Determine unit based on type
            $unit = match($a->type) {
                'Food' => 'kg',
                'Equipment' => 'pcs',
                default => 'pcs',
            };
        @endphp
        {{ $a->resource_name }} {{ rtrim(rtrim(number_format($a->allocated_quantity, 2), '0'), '.') }} {{ $unit }}<br>
    @endforeach
</td>

                            <td>
                                <button class="btn btn-success" wire:click="openAllocationModal({{ $t->task_id }})">Allocate</button>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

   <!-- Main Budget & Resource Management Modal -->
<div class="modal" aria-hidden="{{ $showBudgetModal ? 'false' : 'true' }}">
    <div class="modal-dialog">
        <div class="modal-header">
            Budget & Resource Management
            <button class="btn btn-warning" wire:click="closeBudgetModal">Close</button>
        </div>
        <div class="modal-body">

            <!-- Budgets Section -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <h4>Budgets</h4>
                <button class="btn btn-primary" wire:click="openAddBudgetModal">Add Budget</button>
            </div>

            <!-- Budgets Table -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:1rem;">
                <thead>
                    <tr>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Project</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Phase</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Estimated Cost</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Actual Cost</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($budgets ?? [] as $b)
                        <tr>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ DB::table('projects')->where('project_id', $b->project_id)->value('project_name') }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ DB::table('project_phases')->where('phase_id', $b->phase_id)->value('phase_name') }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ $b->estimated_cost }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ number_format($this->getActualCostForPhase($b->phase_id), 2) }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ number_format($b->estimated_cost - $this->getActualCostForPhase($b->phase_id), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Resources Section -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                <h4>Resources</h4>
                <button class="btn btn-primary" wire:click="openAddResourceModal">Add Resource</button>
            </div>

            <!-- Resources Table -->
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Resource Name</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Type</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Unit Cost</th>
                        <th style="border:1px solid #ddd; padding:0.5rem;">Available Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resources as $r)
                        <tr>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ $r->resource_name }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ $r->type }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ $r->unit_cost }}</td>
                            <td style="border:1px solid #ddd; padding:0.5rem;">{{ $r->availability_quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>
</div>

<!-- Separate Add Budget Modal -->
<div class="modal" style="display: {{ $showAddBudgetModal ? 'flex' : 'none' }};" wire:key="add-budget-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            Add Budget
            <button class="btn btn-warning" wire:click="$set('showAddBudgetModal', false)">Close</button>
        </div>
        <div class="modal-body">

            <div style="display:flex; flex-direction:column; gap:0.5rem;">

                <!-- Project Dropdown -->
                <label>
    Project:
    <select wire:model="newBudgetProjectId" wire:change="loadPhasesForProject($event.target.value)">
        <option value="">-- Select Project --</option>
        @foreach($projects as $p)
            <option value="{{ $p->project_id }}">{{ $p->project_name }}</option>
        @endforeach
    </select>
</label>


                <!-- Phase Dropdown -->
                <label>
                    Phase:
                    <select wire:model="newBudgetPhaseId" wire:key="phase-{{ $newBudgetProjectId }}">
                        <option value="">-- Select Phase --</option>
                        @foreach($phases as $phase)
                            <option value="{{ $phase->phase_id }}">{{ $phase->phase_name }}</option>
                        @endforeach
                    </select>
                </label>

                <!-- Estimated Cost -->
                <label>
                    Estimated Cost:
                    <input type="number" wire:model="newEstimatedCost" step="100">
                </label>

                <!-- Buttons -->
                <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                    <button class="btn btn-success" wire:click="saveNewBudget">Save Budget</button>
                    <button class="btn btn-secondary" wire:click="$set('showAddBudgetModal', false)">Cancel</button>
                </div>

            </div>
        </div>
    </div>
</div>



<!-- Separate Add Resource Modal -->
<div class="modal" aria-hidden="{{ $showAddResourceModal ? 'false' : 'true' }}">
    <div class="modal-dialog">
        <div class="modal-header">
            Add Resource
            <button class="btn btn-warning" wire:click="$set('showAddResourceModal', false)">Close</button>
        </div>
        <div class="modal-body">
            <label>Resource Name:
                <input type="text" wire:model="newResourceName">
            </label>

            <label>Type:
                <select wire:model="newResourceType">
                    <option value="Food">Food</option>
                    <option value="Equipment">Equipment</option>
                    <option value="Other">Other</option>
                </select>
            </label>

            <label>Unit Cost:
                <input type="number" wire:model="newResourceUnitCost" step="100">
            </label>

            <label>Available Quantity:
                <input type="number" wire:model="newResourceQuantity" step="1">
            </label>

            <button class="btn btn-success" wire:click="saveNewResource">Save Resource</button>
            <button class="btn btn-secondary" wire:click="$set('showAddResourceModal', false)">Cancel</button>
        </div>
    </div>
</div>

<div class="modal" style="display: {{ $showAllocationModal ? 'flex' : 'none' }};">
    <div class="modal-dialog">
        <div class="modal-header">
            Allocate Resource
            <button class="btn btn-warning" wire:click="closeAllocationModal">Close</button>
        </div>
        <div class="modal-body">
            <label>Resource:
                <select wire:model="selectedResourceId">
                    <option value="">-- Select Resource --</option>
                    @foreach($resources as $r)
                        <option value="{{ $r->resource_id }}">{{ $r->resource_name }} (Available: {{ $r->availability_quantity }})</option>
                    @endforeach
                </select>
            </label>

            <label>Quantity:
                <input type="number" wire:model="allocatedQuantity" min="0" step="1">
            </label>

            <div style="margin-top:0.5rem;">
                <button class="btn btn-success" wire:click="saveAllocation">Allocate</button>
                <button class="btn btn-secondary" wire:click="closeAllocationModal">Cancel</button>
            </div>
        </div>
    </div>
</div>


</div>
