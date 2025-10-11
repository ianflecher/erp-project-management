<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component
{
    // Modal flags
    public bool $showBudgetModal = false;
    public bool $showAddBudgetModal = false;
    public bool $showAddResourceModal = false;

    // Data arrays
    public array $projects = [];
    public array $phases = [];
    public array $projectTasks = [];
    public array $resources = [];
    public array $budgets = [];
    public array $employees = [];

    // Selected project for modal
    public ?int $selectedProjectId = null;

    // New Budget/Resource fields
    public ?int $newBudgetProjectId = null;
    public ?int $newBudgetPhaseId = null;
    public float $newEstimatedCost = 0;

    public string $newResourceName = '';
    public string $newResourceType = 'Food';
    public float $newResourceUnitCost = 0;
    public float $newResourceQuantity = 0;

    // Mount method to load initial data
    public function mount()
    {
        $this->projects = \DB::table('projects')->get()->toArray();
        $this->resources = \DB::table('resources')->get()->toArray();
        $this->employees = \DB::table('hr_employees')->get()->toArray();
        $this->phases = \DB::table('project_phases')->get()->toArray();
        $this->budgets = \DB::table('budgets')->get()->toArray();

        // Preload tasks grouped by project
        $this->projectTasks = \DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->select('tasks.*', 'project_phases.project_id')
            ->get()
            ->groupBy('project_id')
            ->toArray();
    }

    // Open/close modals
    public function openBudgetModal() { $this->showBudgetModal = true; }
    public function closeBudgetModal() { $this->showBudgetModal = false; }

    // Save new budget
    public function saveNewBudget()
    {
        if(!$this->newBudgetProjectId || !$this->newBudgetPhaseId) return;

        \DB::table('budgets')->insert([
            'project_id' => $this->newBudgetProjectId,
            'phase_id' => $this->newBudgetPhaseId,
            'estimated_cost' => $this->newEstimatedCost,
            'actual_cost' => 0,
            'variance' => $this->newEstimatedCost,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->budgets = \DB::table('budgets')->get()->toArray();
        $this->showAddBudgetModal = false;
        $this->newBudgetPhaseId = null;
        $this->newEstimatedCost = 0;
    }

    // Save new resource
    public function saveNewResource()
    {
        if(!$this->newResourceName || !$this->newResourceQuantity) return;

        \DB::table('resources')->insert([
            'resource_name' => $this->newResourceName,
            'type' => $this->newResourceType,
            'unit_cost' => $this->newResourceUnitCost,
            'availability_quantity' => $this->newResourceQuantity,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->resources = \DB::table('resources')->get()->toArray();
        $this->showAddResourceModal = false;
        $this->newResourceName = '';
        $this->newResourceQuantity = 0;
        $this->newResourceUnitCost = 0;
        $this->newResourceType = 'Food';
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
            <div class="card-title">Types of Resources</div>
            <div class="card-value">{{ count($resources) }}</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin:1rem 0;">
        <button class="btn btn-success" wire:click="openBudgetModal">View Budget & Resources</button>
    </div>

    <!-- Projects & Tasks Table -->
    <div class="data-table">
        <div class="table-header">
            <div>Projects & Tasks</div>
        </div>
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
                            <td>{{ DB::table('project_phases')->where('phase_id', $t->phase_id)->value('phase_name') }}</td>
                            <td>{{ $t->task_name }}</td>
                            <td>
                                @php
                                    $emp = collect($employees)->first(fn($e) => $e->employee_id == $t->assigned_to);
                                @endphp
                                {{ $emp->full_name ?? 'Unassigned' }}
                            </td>
                            <td>
                                @php
                                    $allocs = DB::table('resource_allocations')
                                        ->join('resources','resources.resource_id','=','resource_allocations.resource_id')
                                        ->where('resource_allocations.task_id',$t->task_id)
                                        ->select('resources.resource_name','resource_allocations.allocated_quantity')
                                        ->get();
                                @endphp
                                @foreach($allocs as $a)
                                    {{ $a->resource_name }} ({{ $a->allocated_quantity }})<br>
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

    <!-- Budget & Resource Modal -->
    <div class="modal" aria-hidden="{{ $showBudgetModal ? 'false' : 'true' }}">
        <div class="modal-dialog">
            <div class="modal-header">
                Budget & Resource Management
                <button class="btn btn-warning" wire:click="closeBudgetModal">Close</button>
            </div>

            <div class="modal-body">

                <!-- Select Project -->
                <div style="margin-bottom:1rem;">
                    <label>Select Project:
                        <select wire:model="selectedProjectId">
                            <option value="">-- Select Project --</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->project_id }}">{{ $p->project_name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if($selectedProjectId)
                    @php
                        $project = collect($projects)->first(fn($p) => $p->project_id == $selectedProjectId);
                        $projectBudgets = collect($budgets)->where('project_id', $selectedProjectId);
                        $projectPhases = collect($phases)->where('project_id', $selectedProjectId);
                    @endphp

                    <!-- Project Total Budget -->
                    <h4>Project Budget</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Total Budget</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $project->project_name }}</td>
                                <td>{{ number_format($project->budget_total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Add Budget Form -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin:1rem 0 0.5rem;">
                        <h4>Phase Budgets</h4>
                        <button class="btn btn-primary" wire:click="$set('showAddBudgetModal', true)">Add Phase Budget</button>
                    </div>

                    @if($showAddBudgetModal)
                        <div class="form-grid" style="margin-bottom:1rem;">
                            <label>Phase:
                                <select wire:model="newBudgetPhaseId">
                                    <option value="">-- Select Phase --</option>
                                    @foreach($projectPhases as $phase)
                                        <option value="{{ $phase->phase_id }}">{{ $phase->phase_name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label>Estimated Cost:
                                <input type="number" wire:model="newEstimatedCost" step="0.01">
                            </label>

                            <div style="grid-column: span 2;">
                                <button class="btn btn-success" wire:click="saveNewBudget">Save Budget</button>
                                <button class="btn btn-secondary" wire:click="$set('showAddBudgetModal', false)">Cancel</button>
                            </div>
                        </div>
                    @endif

                    <!-- Phase Budgets Table -->
                    @if($projectBudgets->count())
                        <table>
                            <thead>
                                <tr>
                                    <th>Phase</th>
                                    <th>Estimated Cost</th>
                                    <th>Actual Cost</th>
                                    <th>Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projectBudgets as $b)
                                    <tr>
                                        <td>{{ DB::table('project_phases')->where('phase_id', $b->phase_id)->value('phase_name') }}</td>
                                        <td>{{ $b->estimated_cost }}</td>
                                        <td>{{ $b->actual_cost }}</td>
                                        <td>{{ $b->variance }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <!-- Resources Section -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin:1rem 0 0.5rem;">
                        <h4>Resources</h4>
                        <button class="btn btn-primary" wire:click="$set('showAddResourceModal', true)">Add Resource</button>
                    </div>

                    <!-- Add Resource Form -->
                    @if($showAddResourceModal)
                        <div class="form-grid" style="margin-bottom:1rem;">
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
                                <input type="number" wire:model="newResourceUnitCost" step="0.01">
                            </label>

                            <label>Available Quantity:
                                <input type="number" wire:model="newResourceQuantity" step="0.01">
                            </label>

                            <div style="grid-column: span 2;">
                                <button class="btn btn-success" wire:click="saveNewResource">Save Resource</button>
                                <button class="btn btn-secondary" wire:click="$set('showAddResourceModal', false)">Cancel</button>
                            </div>
                        </div>
                    @endif

                    <!-- Resources Table -->
                    <table>
                        <thead>
                            <tr>
                                <th>Resource Name</th>
                                <th>Type</th>
                                <th>Unit Cost</th>
                                <th>Available Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($resources as $r)
                                <tr>
                                    <td>{{ $r->resource_name }}</td>
                                    <td>{{ $r->type }}</td>
                                    <td>{{ $r->unit_cost }}</td>
                                    <td>{{ $r->availability_quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                @endif

            </div>
        </div>
    </div>

</div>
