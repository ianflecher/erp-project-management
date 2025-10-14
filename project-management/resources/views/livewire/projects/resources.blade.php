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
    public bool $showAssignMemberModal = false;
    public ?int $currentTaskId = null;
    public $showViewResourcesModal = false;
    public $resources = [];
    public $showEditAllocationModal = false;
    public $editingAllocation = [];
    public $showEditResourceModal = false;
    public $editingResource;
    

    public ?int $selectedProjectFilter = null; // null means show all projects


    public ?int $task_assigned_to = null;
    public ?int $editTaskAssignedTo = null;

    public ?int $newBudgetProjectId = null;
    public ?int $newBudgetPhaseId = null;
    public float $newEstimatedCost = 0;

    public string $newResourceName = '';
    public string $newResourceType = 'Food';
    public float $newResourceUnitCost = 0;
    public float $newResourceQuantity = 0;

    // Employees & Resources
    public array $employees = [];
    
    public array $budgets = [];
    public ?int $currentProjectId = null;
    public array $projectMembers = [];



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
    $this->loadProjects();       // loads $this->projects as array
    $this->loadEmployees();
    $this->loadResources();
    $this->loadTasksForAllProjects();

    // Loop through projects to get their members
    foreach ($this->projects as $p) {
        $memberIds = explode(',', $p->project_member_id ?? ''); // array of IDs

        $this->projectMembers[$p->project_id] = DB::table('hr_employees')
    ->whereIn('employee_id', $memberIds)
    ->select('employee_id', 'full_name')
    ->get()
    ->toArray();

    }
}

    public function openEditResourceModal($resourceId)
{
    $resource = DB::table('resources')->where('resource_id', $resourceId)->first();
    if ($resource) {
        $this->editingResource = (array) $resource;
        $this->showEditResourceModal = true;
    }
}

public function closeEditResourceModal()
{
    $this->showEditResourceModal = false;
    $this->editingResource = null; // optional, clear the editing data
}

public function updateResource()
{
    $validated = $this->validate([
        'editingResource.resource_name' => 'required|string|max:255',
        'editingResource.type' => 'required|string',
        'editingResource.unit_cost' => 'required|numeric|min:0',
        'editingResource.availability_quantity' => 'required|numeric|min:0',
        'editingResource.status' => 'required|string',
    ]);

    DB::table('resources')
        ->where('resource_id', $this->editingResource['resource_id'])
        ->update($this->editingResource);

    $this->showEditResourceModal = false;
    $this->resources = DB::table('resources')->get(); // reload
}

// Delete resource
public function deleteResource($resourceId)
{
    DB::table('resources')->where('resource_id', $resourceId)->delete();
    $this->resources = DB::table('resources')->get(); // reload
}

    public function openEditAllocationModal($allocationId)
{
    $allocation = DB::table('resource_allocations')
        ->where('allocation_id', $allocationId)
        ->first();

    if ($allocation) {
        $this->editingAllocation = (array) $allocation;
        $this->showEditAllocationModal = true;
    }
}

public function updateAllocation()
{
    $allocation = DB::table('resource_allocations')
        ->where('allocation_id', $this->editingAllocation['allocation_id'])
        ->first();

    if (!$allocation) return;

    $resource = DB::table('resources')
        ->where('resource_id', $allocation->resource_id)
        ->first();

    $oldQty = $allocation->allocated_quantity;
    $newQty = $this->editingAllocation['allocated_quantity'];

    $diff = $newQty - $oldQty;

    if ($diff > $resource->availability_quantity) {
    $this->addError('editingAllocation.allocated_quantity', "Stocks We Have   ({$resource->availability_quantity})");

    // Dispatch browser event to auto-clear error
    $this->dispatchBrowserEvent('clear-allocation-error', ['field' => 'editingAllocation.allocated_quantity']);
    return;
}


    // Update allocation
    DB::table('resource_allocations')
        ->where('allocation_id', $allocation->allocation_id)
        ->update([
            'allocated_quantity' => $newQty,
            'updated_at' => now(),
        ]);

    // Update resource availability
    DB::table('resources')
        ->where('resource_id', $resource->resource_id)
        ->update([
            'availability_quantity' => $resource->availability_quantity - $diff,
        ]);

    $this->showEditAllocationModal = false;
    $this->editingAllocation = [];
    $this->resetValidation(); // Clear error
    $this->loadTasksForAllProjects();
}



public function deleteAllocation($allocationId)
{
    // Get the allocation first
    $allocation = DB::table('resource_allocations')
        ->where('allocation_id', $allocationId)
        ->first();

    if (!$allocation) return;

    // Add the allocated quantity back to the resource
    DB::table('resources')
        ->where('resource_id', $allocation->resource_id)
        ->increment('availability_quantity', $allocation->allocated_quantity);

    // Delete the allocation
    DB::table('resource_allocations')
        ->where('allocation_id', $allocationId)
        ->delete();

    // Reload tasks/resources
    $this->loadTasksForAllProjects();
}


public function getFilteredProjectsProperty()
{
    $filter = (int) $this->selectedProjectFilter;
    if ($filter > 0) {
        return collect($this->projects)
            ->where('project_id', $filter)
            ->all();
    }
    return $this->projects;
}




    public function openAssignMemberModal(int $taskId, int $projectId)
{
    $this->currentTaskId = $taskId;
    $this->currentProjectId = $projectId;
    $this->selectedEmployeeId = null;

    // Get the project
    $project = collect($this->projects)->first(fn($p) => $p->project_id == $projectId);

    // Get member IDs as array
    $memberIds = explode(',', $project->project_member_id ?? '');

    // Fetch members from hr_employees table
    $this->projectMembers[$projectId] = DB::table('hr_employees')
        ->whereIn('employee_id', $memberIds)
        ->select('employee_id', 'full_name')
        ->get()
        ->toArray();

    $this->showAssignMemberModal = true;
}

public function assignMember()
{
    if ($this->currentTaskId && $this->selectedEmployeeId) {
        $task = DB::table('tasks')->where('task_id', $this->currentTaskId)->first();

        if ($task) {
            $assignedIds = $task->assigned_to ? explode(',', $task->assigned_to) : [];

            if (!in_array($this->selectedEmployeeId, $assignedIds)) {
                $assignedIds[] = $this->selectedEmployeeId;

                DB::table('tasks')
                    ->where('task_id', $this->currentTaskId)
                    ->update(['assigned_to' => implode(',', $assignedIds)]);
            }
        }

        $this->showAssignMemberModal = false;
        $this->loadTasksForAllProjects();
        $this->selectedEmployeeId = null;
        $this->currentTaskId = null;
    }
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
    $this->resources = DB::table('resources')->get();
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
    $unitCost = DB::table('resources')->where('resource_id', $this->selectedResourceId)->value('unit_cost');
$totalCost = $this->allocatedQuantity * $unitCost;
DB::table('resource_allocations')->insert([
    'task_id' => $this->selectedTaskId,
    'resource_id' => $this->selectedResourceId,
    'allocated_quantity' => $this->allocatedQuantity,
    'allocation_date' => now(),
    'cost' => $totalCost,
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
    $this->projectTasks = []; // reset

    foreach ($this->projects as $p) {
        $tasks = DB::table('tasks as t')
            ->join('project_phases as ph', 't.phase_id', '=', 'ph.phase_id')
            ->where('ph.project_id', $p->project_id)
            ->select('t.*', 'ph.phase_name')
            ->orderBy('t.task_id') // optional ordering
            ->get()
            ->toArray(); // convert collection to array

        $this->projectTasks[$p->project_id] = $tasks;
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
    <!-- Resources Section -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
    <h4></h4>
    <div class="flex gap-2">
    <a href="{{ route('projects.viewresources') }}"
       style="
           background-color: #22c55e;
           color: #fff;
           border: none;
           border-radius: 8px;
           padding: 10px 20px;
           font-size: 1rem;
           font-weight: 600;
           cursor: pointer;
           text-decoration: none;
           transition: background 0.2s ease, transform 0.1s ease;
           display: inline-block;
       "
       onmouseover="this.style.backgroundColor='#16a34a'; this.style.transform='translateY(-2px)'"
       onmouseout="this.style.backgroundColor='#22c55e'; this.style.transform='none'">
       👁 View
    </a>
</div>

</div>

@if($showViewResourcesModal)
<div class="friesday-modal" aria-hidden="false">
    <div class="friesday-modal-box">
        <!-- Header -->
        <div class="friesday-modal-header">
            <h3>All Resources</h3>
            <button wire:click="closeViewResourcesModal" class="friesday-modal-close">&times;</button>
        </div>

        <!-- Body -->
        <div class="friesday-modal-body">
            <div class="friesday-table-container">
                <table class="friesday-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th class="friesday-text-right">Unit Cost</th>
                            <th class="friesday-text-right">Availability</th>
                            <th>Status</th>
                            <th class="friesday-text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($resources as $r)
                        <tr>
                            <td>{{ $r->resource_name }}</td>
                            <td>{{ $r->type }}</td>
                            <td class="friesday-text-right">{{ number_format($r->unit_cost, 2) }}</td>
                            <td class="friesday-text-right">{{ rtrim(rtrim(number_format($r->availability_quantity, 2), '0'), '.') }}</td>
                            <td>{{ $r->status }}</td>
                            <td class="friesday-text-center">
                                <button class="friesday-btn friesday-btn-warning"
                                        wire:click="openEditResourceModal({{ $r->resource_id }})">Edit</button>
                                <button class="friesday-btn friesday-btn-danger"
                                        onclick="if(confirm('Delete this resource?')) @this.call('deleteResource', {{ $r->resource_id }})">Delete</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="friesday-empty">No resources found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="friesday-modal-footer">
            <button wire:click="closeViewResourcesModal">Close</button>
        </div>
    </div>
</div>
@endif

@if($showEditResourceModal)
<div class="modal" aria-hidden="false">
    <div class="modal-dialog w-full max-w-md bg-white rounded-lg shadow-lg">
        <div class="modal-header flex justify-between items-center px-4 py-3 bg-yellow-600 text-white">
            <h3>Edit Resource</h3>
            <button wire:click="$set('showEditResourceModal', false)" class="text-2xl">&times;</button>
        </div>
        <div class="modal-body p-4 space-y-2">
            <label>Name
                <input type="text" wire:model="editingResource.resource_name" class="w-full border rounded px-2 py-1">
            </label>
            <label>Type
                <input type="text" wire:model="editingResource.type" class="w-full border rounded px-2 py-1">
            </label>
            <label>Unit Cost
                <input type="number" min="0" step="0.01" wire:model="editingResource.unit_cost" class="w-full border rounded px-2 py-1">
            </label>
            <label>Availability
                <input type="number" min="0" step="0.01" wire:model="editingResource.availability_quantity" class="w-full border rounded px-2 py-1">
            </label>
            <label>Status
                <input type="text" wire:model="editingResource.status" class="w-full border rounded px-2 py-1">
            </label>
        </div>
        <div class="modal-footer flex justify-end gap-2 px-4 py-3 bg-gray-100">
            <button class="btn btn-secondary px-4 py-1 rounded" wire:click="$set('showEditResourceModal', false)">Cancel</button>
            <button class="btn btn-primary px-4 py-1 rounded" wire:click="updateResource">Save</button>
        </div>
    </div>
</div>
@endif



   @if($showEditAllocationModal)
    <div class="modal" aria-hidden="false">
        <div class="modal-dialog">
            <!-- Modal Header -->
            <div class="modal-header">
                <h3>Edit Resource Allocation</h3>
                <button wire:click="$set('showEditAllocationModal', false)" 
                        style="background:none;border:none;color:#fff;font-size:1.2rem;">
                    &times;
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">

                <div x-data 
     x-on:clear-allocation-error.window="
         const field = $event.detail.field;
         setTimeout(() => {
             @this.setError(field, null);
         }, 3000);
     ">
    <label for="allocatedQuantity">Allocated Quantity</label>
    <input id="allocatedQuantity" type="number" min="0" step="0.01" 
           wire:model="editingAllocation.allocated_quantity" 
           class="w-full border px-2 py-1 rounded">

    @error('editingAllocation.allocated_quantity')
        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>

            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button class="btn btn-secondary" wire:click="$set('showEditAllocationModal', false)">Cancel</button>
                <button class="btn btn-primary" wire:click="updateAllocation">Save</button>
            </div>
        </div>
    </div>
@endif

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
@foreach($this->filteredProjects as $p)
    @php
        $tasks = $projectTasks[$p->project_id] ?? [];
    @endphp

    @if(count($tasks) > 0)
        @foreach($tasks as $t)
            <tr>
                <td>{{ $p->project_name }}</td>
                <td>{{ $t->phase_name }}</td>
                <td>{{ $t->task_name }}</td>
                <td>
@php
    $assignedIds = explode(',', $t->assigned_to ?? '');
    $assignedEmployees = collect($employees)
        ->whereIn('employee_id', $assignedIds)
        ->pluck('full_name')
        ->toArray();
@endphp

@if(count($assignedEmployees) > 0)
    {{ implode(', ', $assignedEmployees) }}
@else
    Unassigned
@endif
</td>

              <td>
    @php
        $allocs = DB::table('resource_allocations')
            ->join('resources', 'resources.resource_id', '=', 'resource_allocations.resource_id')
            ->where('resource_allocations.task_id', $t->task_id)
            ->select(
                'resource_allocations.allocation_id',
                'resources.resource_name',
                'resources.type',
                'resource_allocations.allocated_quantity'
            )
            ->get();
    @endphp

    @foreach($allocs as $a)
        @php
            $unit = match($a->type) {
                'Food' => 'kg',
                'Equipment' => 'pcs',
                default => 'pcs',
            };
        @endphp

        <div class="flex items-center justify-between mb-2 p-2 border rounded-md">
            <span>{{ $a->resource_name }} {{ rtrim(rtrim(number_format($a->allocated_quantity, 2), '0'), '.') }} {{ $unit }}</span>

            <span class="flex gap-2">
                <button class="btn btn-warning px-3 py-1 rounded-md text-white hover:bg-yellow-500 transition"
                        wire:click="openEditAllocationModal({{ $a->allocation_id }})">
                    Edit
                </button>
                <button class="btn btn-danger px-3 py-1 rounded-md text-white hover:bg-red-600 transition"
                        onclick="if(confirm('Delete this allocation?')) @this.call('deleteAllocation', {{ $a->allocation_id }})">
                    Delete
                </button>
            </span>
        </div>
    @endforeach

    @if(count($allocs) === 0)
        <span class="text-gray-400 italic text-sm">No resources allocated</span>
    @endif
</td>




                <td class="flex gap-2">
    <!-- Allocate -->
    <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition" 
            wire:click="openAllocationModal({{ $t->task_id }})">
        Allocate
    </button>

    <!-- Assign Member -->
    <button class="btn btn-primary px-3 py-1 rounded-md text-white hover:bg-blue-600 transition" 
            wire:click="openAssignMemberModal({{ $t->task_id }}, {{ $p->project_id }})">
        Assign
    </button>
</td>

            </tr>
        @endforeach
    @else
        <tr>
            <td>{{ $p->project_name }}</td>
            <td colspan="5" class="text-center">No tasks yet</td>
        </tr>
    @endif
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
            <button class="btn btn-warning" class="btn btn-warning"wire:click="closeAllocationModal">Close</button>
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

<div class="modal" style="display: {{ $showAssignMemberModal ? 'flex' : 'none' }};">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Assign Member</h3>
            <button class="btn btn-warning" wire:click="$set('showAssignMemberModal', false)">Close</button>
        </div>
        <div class="modal-body">
            @php
$assignedIds = collect($projectTasks[$currentProjectId] ?? [])
    ->pluck('assigned_to')
    ->filter() // remove nulls
    ->all();
@endphp

<select wire:model="selectedEmployeeId">
    <option value="">-- Select Member --</option>
    @foreach($projectMembers[$currentProjectId] ?? [] as $member)
        @if(!in_array($member->employee_id, $assignedIds))
            <option value="{{ $member->employee_id }}">{{ $member->full_name }}</option>
        @endif
    @endforeach
</select>



        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" wire:click="assignMember">Assign</button>
        </div>
    </div>
</div>
</div>
