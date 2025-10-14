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
    public $totalCost = 0;
    public $allocatedQuantity = 0;
    public $selectedResourceId = null;
    public $resourceUnitCost = 0;
    public $remainingBudget = 0;
    public array $editingBudget = [];
    public ?int $selectedTaskId = null;



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
    public ?int $selectedEmployeeId = null;
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
    // Open Add Budget Modal
public function openAddBudgetModal($taskId)
{
    $this->selectedTaskId = $taskId;
    $this->editingBudget = [
        'budget_id' => null,  // No budget yet
        'task_id' => $taskId,
        'estimated_cost' => 0,
        'actual_cost' => 0,
        'variance' => 0,
    ];
    $this->showBudgetModal = true;
}

// Open Edit Budget Modal
public function openEditBudgetModal($budgetId)
{
    $budget = DB::table('budgets')->where('budget_id', $budgetId)->first();

    if (!$budget) {
        $this->addError('editingBudget.estimated_cost', 'Budget not found.');
        return;
    }

    $this->editingBudget = (array) $budget; // budget_id will exist here
    $this->showBudgetModal = true;
}


public function addBudget()
{
    // Fetch task with phase to get project_id
    $task = DB::table('tasks as t')
        ->join('project_phases as ph', 't.phase_id', '=', 'ph.phase_id')
        ->select('t.*', 'ph.project_id')
        ->where('t.task_id', $this->editingBudget['task_id'])
        ->first();

    if (!$task) {
        $this->addError('editingBudget.estimated_cost', 'Cannot find project for this task.');
        return;
    }

    $budgetId = DB::table('budgets')->insertGetId([
        'task_id' => $task->task_id,
        'phase_id' => $task->phase_id ?? 0,
        'project_id' => $task->project_id,
        'estimated_cost' => $this->editingBudget['estimated_cost'] ?? 0,
        'actual_cost' => 0,
        'variance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->editingBudget['budget_id'] = $budgetId;
    $this->showBudgetModal = false;
    session()->flash('success', 'Budget added successfully!');
}



public function saveBudget()
{
    $this->validate([
        'editingBudget.estimated_cost' => 'required|numeric|min:0',
    ]);

    // Keep actual_cost and variance intact
    $this->editingBudget['variance'] = ($this->editingBudget['actual_cost'] ?? 0) - $this->editingBudget['estimated_cost'];

    DB::table('budgets')
        ->where('budget_id', $this->editingBudget['budget_id'])
        ->update([
            'estimated_cost' => $this->editingBudget['estimated_cost'],
            'variance' => $this->editingBudget['variance'],
            'updated_at' => now(),
        ]);

    $this->showBudgetModal = false;
    $this->editingBudget = [];
    session()->flash('success', 'Budget updated successfully!');
}


private function getProjectIdByTask($taskId)
{
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task) return null;

    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    return $phase ? $phase->project_id : null;
}


private function getPhaseIdByTask($taskId)
{
    return DB::table('tasks')->where('task_id', $taskId)->value('phase_id');
}


    public function computeEditTotalCost()
{
    // 1️⃣ Check required fields
    if (!isset($this->editingAllocation['allocated_quantity'], $this->editingAllocation['resource_id'], $this->editingAllocation['task_id'])) {
        $this->editingAllocation['total_cost'] = 0;
        $this->editingAllocation['remaining_budget'] = 0;
        return;
    }

    // 2️⃣ Fetch resource
    $resource = DB::table('resources')
        ->where('resource_id', $this->editingAllocation['resource_id'])
        ->first();

    if (!$resource) {
        $this->editingAllocation['total_cost'] = 0;
        $this->editingAllocation['remaining_budget'] = 0;
        return;
    }

    // 3️⃣ Calculate total cost
    $this->editingAllocation['total_cost'] = $this->editingAllocation['allocated_quantity'] * $resource->unit_cost;

    // 4️⃣ Fetch task → phase → project
    $task = DB::table('tasks')->where('task_id', $this->editingAllocation['task_id'])->first();
    if (!$task) {
        $this->editingAllocation['remaining_budget'] = 0;
        return;
    }

    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    if (!$phase) {
        $this->editingAllocation['remaining_budget'] = 0;
        return;
    }

    $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
    if (!$project) {
        $this->editingAllocation['remaining_budget'] = 0;
        return;
    }

    // 5️⃣ Calculate current spent budget
    $currentSpent = DB::table('resource_allocations as ra')
        ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
        ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id')
        ->where('pp.project_id', $project->project_id)
        ->sum('ra.cost');

    // 6️⃣ Subtract current allocation cost so we don't double-count
    $originalQty = $this->editingAllocation['original_quantity'] ?? 0;
    $currentSpent -= $originalQty * $resource->unit_cost;

    // 7️⃣ Set remaining budget
    $this->editingAllocation['remaining_budget'] = $project->budget_total - $currentSpent;
}


   public function updatedAllocatedQuantity($value)
{
    $this->computeTotalCost();
}

    public function updatedSelectedResourceId($value)
{
    if ($value) {
        $resource = collect($this->resources)->firstWhere('resource_id', $value);
        $this->resourceUnitCost = $resource ? $resource->unit_cost : 0;
    } else {
        $this->resourceUnitCost = 0;
    }

    $this->computeTotalCost();
}

public function computeTotalCost()
{
    if ($this->selectedResourceId && is_numeric($this->allocatedQuantity)) {
        $unitCost = DB::table('resources')
            ->where('resource_id', $this->selectedResourceId)
            ->value('unit_cost');

        $this->totalCost = $unitCost * $this->allocatedQuantity;
    } else {
        $this->totalCost = 0;
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
    $allocation = DB::table('resource_allocations as ra')
        ->join('resources as r', 'ra.resource_id', '=', 'r.resource_id')
        ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
        ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id') // join phases
        ->select(
            'ra.*',
            'r.resource_name',
            'r.unit_cost',
            'r.type',
            't.task_name',
            'pp.project_id', // get project via phase
            'ra.allocated_quantity as original_quantity'
        )
        ->where('ra.allocation_id', $allocationId)
        ->first();

    if (!$allocation) {
        $this->addError('editingAllocation', 'Allocation not found.');
        return;
    }

    $project = DB::table('projects')->where('project_id', $allocation->project_id)->first();
    if (!$project) {
        $this->addError('editingAllocation', 'Project not found.');
        return;
    }

    // Compute current spent excluding this allocation
    $currentSpent = DB::table('resource_allocations as ra')
        ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
        ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id')
        ->where('pp.project_id', $project->project_id)
        ->where('ra.allocation_id', '!=', $allocationId)
        ->sum('ra.cost');

    $remainingBudget = $project->budget_total - $currentSpent;

    $this->editingAllocation = [
        'allocation_id' => $allocation->allocation_id,
        'resource_id' => $allocation->resource_id,
        'resource_name' => $allocation->resource_name,
        'unit_cost' => $allocation->unit_cost,
        'type' => $allocation->type,
        'task_id' => $allocation->task_id,
        'project_id' => $allocation->project_id,
        'allocated_quantity' => $allocation->allocated_quantity,
        'original_quantity' => $allocation->original_quantity,
        'total_cost' => $allocation->allocated_quantity * $allocation->unit_cost,
        'remaining_budget' => $remainingBudget,
    ];

    $this->showEditAllocationModal = true;
}


    public function updateAllocation()
{
    // 1️⃣ Fetch allocation
    $allocation = DB::table('resource_allocations')
        ->where('allocation_id', $this->editingAllocation['allocation_id'])
        ->first();

    if (!$allocation) {
        $this->addError('editingAllocation.allocated_quantity', "Allocation not found.");
        return;
    }

    // 2️⃣ Fetch resource
    $resource = DB::table('resources')->where('resource_id', $allocation->resource_id)->first();
    if (!$resource) {
        $this->addError('editingAllocation.allocated_quantity', "Resource not found.");
        return;
    }

    // 3️⃣ Fetch task → phase → project
    $task = DB::table('tasks')->where('task_id', $allocation->task_id)->first();
    if (!$task) {
        $this->addError('editingAllocation.allocated_quantity', "Task not found.");
        return;
    }

    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    if (!$phase) {
        $this->addError('editingAllocation.allocated_quantity', "Phase not found.");
        return;
    }

    $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
    if (!$project) {
        $this->addError('editingAllocation.allocated_quantity', "Project not found.");
        return;
    }

    // 4️⃣ Calculate differences
    $oldQty = $allocation->allocated_quantity;
    $newQty = $this->editingAllocation['allocated_quantity'];
    $diffQty = $newQty - $oldQty;
    $diffCost = $diffQty * $resource->unit_cost;

    // 5️⃣ Check stock availability
    if ($diffQty > $resource->availability_quantity) {
        $this->addError('editingAllocation.allocated_quantity', "Insufficient stock. Available: {$resource->availability_quantity}");
        return;
    }

    // 6️⃣ Calculate remaining budget
    $currentSpent = DB::table('resource_allocations as ra')
        ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
        ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id')
        ->where('pp.project_id', $project->project_id)
        ->sum('ra.cost');

    // Subtract the current allocation's cost so we don't double-count
    $currentSpent -= $allocation->cost;

    $remainingBudget = $project->budget_total - $currentSpent;

    if (($newQty * $resource->unit_cost) > $remainingBudget) {
        $this->addError('editingAllocation.allocated_quantity', 
            "Cannot update. Remaining project budget: ₱ " . number_format($remainingBudget, 2)
        );
        return;
    }

    // 7️⃣ Update allocation
    DB::table('resource_allocations')
        ->where('allocation_id', $allocation->allocation_id)
        ->update([
            'allocated_quantity' => $newQty,
            'cost' => $newQty * $resource->unit_cost,
            'updated_at' => now(),
        ]);

    // 8️⃣ Update resource availability
    DB::table('resources')
        ->where('resource_id', $resource->resource_id)
        ->update([
            'availability_quantity' => $resource->availability_quantity - $diffQty,
        ]);

    // 9️⃣ Reset modal & refresh
    $this->showEditAllocationModal = false;
    $this->editingAllocation = [];
    $this->resetValidation();
    $this->loadTasksForAllProjects();

    session()->flash('success', 'Allocation updated successfully!');
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
    $this->selectedTaskId = $taskId;

    // Fetch task
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task) return;

    // Fetch phase
    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    if (!$phase) return;

    // Fetch project
    $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
    if (!$project) return;

    // Compute remaining budget
    $currentSpent = DB::table('resource_allocations as ra')
        ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
        ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id')
        ->where('pp.project_id', $project->project_id)
        ->sum('ra.cost');

    $this->remainingBudget = $project->budget_total - $currentSpent;

    // Reset fields
    $this->selectedResourceId = null;
    $this->allocatedQuantity = 0;
    $this->totalCost = 0;
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
    // 1️⃣ Fetch resource
    $resource = DB::table('resources')
        ->where('resource_id', $this->selectedResourceId)
        ->first();

    if (!$resource) {
        $this->addError('allocatedQuantity', 'Resource not found.');
        return;
    }

    // 2️⃣ Fetch task
    $task = DB::table('tasks')->where('task_id', $this->selectedTaskId)->first();
    if (!$task) {
        $this->addError('allocatedQuantity', 'Task not found.');
        return;
    }

    // 3️⃣ Fetch phase
    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    if (!$phase) {
        $this->addError('allocatedQuantity', 'Project phase not found.');
        return;
    }

    // 4️⃣ Fetch project
    $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
    if (!$project) {
        $this->addError('allocatedQuantity', 'Project not found.');
        return;
    }

    // 5️⃣ Compute total cost
    $totalCost = $this->allocatedQuantity * $resource->unit_cost;

    // 6️⃣ Check stock
    if ($this->allocatedQuantity > $resource->availability_quantity) {
        $this->addError('allocatedQuantity', "Insufficient stock. Available: {$resource->availability_quantity}");
        return;
    }

    // 7️⃣ Compute current spent & remaining budget
    $currentSpent = DB::table('resource_allocations as ra')
        ->join('tasks as t', 'ra.task_id', '=', 't.task_id')
        ->join('project_phases as pp', 't.phase_id', '=', 'pp.phase_id')
        ->where('pp.project_id', $project->project_id)
        ->sum('ra.cost');

    $remainingBudget = $project->budget_total - $currentSpent;

    if ($totalCost > $remainingBudget) {
        $this->addError('allocatedQuantity', 
            "Cannot allocate. Remaining project budget: ₱ " . number_format($remainingBudget, 2)
        );
        return;
    }

    // 8️⃣ Insert allocation
    DB::table('resource_allocations')->insert([
        'task_id' => $task->task_id,
        'resource_id' => $resource->resource_id,
        'allocated_quantity' => $this->allocatedQuantity,
        'allocation_date' => now(),
        'cost' => $totalCost,
    ]);

    // 9️⃣ Update resource availability
    DB::table('resources')
        ->where('resource_id', $resource->resource_id)
        ->decrement('availability_quantity', $this->allocatedQuantity);

    // 🔟 Reset modal & fields
    $this->loadResources();
    $this->selectedTaskId = null;
    $this->selectedResourceId = null;
    $this->allocatedQuantity = 0;
    $this->totalCost = 0;
    $this->remainingBudget = 0;
    $this->showAllocationModal = false;

    session()->flash('success', 'Allocation saved successfully!');
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

<!-- Edit Allocation Modal -->
@if($showEditAllocationModal)
<div class="resources-modal" style="display:flex;">
    <div class="resources-modal-box">
        <!-- Header -->
        <div class="resources-modal-header">
            <h3>Edit Resource Allocation</h3>
            <button class="resources-close-btn" wire:click="$set('showEditAllocationModal', false)">&times;</button>
        </div>

        <!-- Body -->
        <div class="resources-form-grid">
            <!-- Resource (readonly) -->
            <label>
                Resource
                <input type="text" readonly value="{{ $editingAllocation['resource_name'] ?? '' }}">
            </label>

            <!-- Quantity -->
            <label>
                Quantity
                <input type="number" min="0" step="0.01"
                       wire:model.debounce.200ms="editingAllocation.allocated_quantity"
                       wire:keyup="computeEditTotalCost"
                       class="border rounded-md px-3 py-2 w-full" />
            </label>

            <!-- Total Cost -->
            <label>
                Total Cost
                <input type="text" readonly value="₱ {{ number_format($editingAllocation['total_cost'] ?? 0, 2) }}">
            </label>

            <!-- Remaining Budget -->
            <label>
                Remaining Budget
                <input type="text" readonly value="₱ {{ number_format($editingAllocation['remaining_budget'] ?? 0, 2) }}">
            </label>

            <!-- Error Messages -->
            @if(isset($editingAllocation['total_cost']) && $editingAllocation['total_cost'] > ($editingAllocation['remaining_budget'] ?? 0))
                <p class="text-red-600 text-sm mt-1">
                    Error: Allocation exceeds remaining budget!
                </p>
            @endif

            @error('editingAllocation.allocated_quantity')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <!-- Footer -->
        <div class="resources-modal-actions">
            <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition"
                    wire:click="updateAllocation">
                Save
            </button>
            <button class="resources-btn-gray"
                    wire:click="$set('showEditAllocationModal', false)">
                Cancel
            </button>
        </div>
    </div>
</div>
@endif


    <!-- Projects Table -->
    <div class="budgetresource-table-container">
    <div class="budgetresource-table-header">Projects & Tasks</div>
    <table class="budgetresource-table">
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
            @php $tasks = $projectTasks[$p->project_id] ?? []; @endphp
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
                                <div class="budgetresource-alloc-row">
                                    <span>{{ $a->resource_name }} {{ rtrim(rtrim(number_format($a->allocated_quantity, 2), '0'), '.') }} {{ $unit }}</span>
                                    <span class="budgetresource-actions">
                                        <button class="budgetresource-btn-warning"
                                                wire:click="openEditAllocationModal({{ $a->allocation_id }})">Edit</button>
                                        <button class="budgetresource-btn-danger"
                                                onclick="if(confirm('Delete this allocation?')) @this.call('deleteAllocation', {{ $a->allocation_id }})">Delete</button>
                                    </span>
                                </div>
                            @endforeach
                            @if(count($allocs) === 0)
                                <span class="budgetresource-no-alloc">No resources allocated</span>
                            @endif
                        </td>
                        <td class="budgetresource-actions-cell">
                            <button class="budgetresource-btn-success"
                                    wire:click="openAllocationModal({{ $t->task_id }})">Allocate</button>
                            <button class="budgetresource-btn-primary"
                                    wire:click="openAssignMemberModal({{ $t->task_id }}, {{ $p->project_id }})">Assign</button>

                            @php
                                $taskBudget = DB::table('budgets')->where('task_id', $t->task_id)->first();
                            @endphp
                            @if($taskBudget)
                                <button class="budgetresource-btn-warning"
                                        wire:click="openEditBudgetModal({{ $taskBudget->budget_id }})">Edit Budget</button>
                            @else
                                <button class="budgetresource-btn-success"
                                        wire:click="openAddBudgetModal({{ $t->task_id }})">Add Budget</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>{{ $p->project_name }}</td>
                    <td colspan="5" class="budgetresource-no-tasks">No tasks yet</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>
</div>


<!-- Allocate Resource Modal -->
<div class="modal" style="display: {{ $showAllocationModal ? 'flex' : 'none' }};">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="text-lg font-semibold">Allocate Resource</h3>
            <button class="btn btn-warning" wire:click="closeAllocationModal">× Close</button>
        </div>

        <div class="modal-body space-y-4">
            <!-- Resource -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Resource</label>
                <select wire:model="selectedResourceId" wire:change="computeTotalCost" class="w-full border rounded-md px-3 py-2">
                    <option value="">-- Select Resource --</option>
                    @foreach($resources as $r)
                        <option value="{{ $r->resource_id }}">
                            {{ $r->resource_name }} (Available: {{ $r->availability_quantity }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Quantity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" wire:model="allocatedQuantity" wire:keyup="computeTotalCost" min="0" step="1"
                       class="w-full border rounded-md px-3 py-2" />
            </div>

            <!-- Total Cost & Remaining Budget -->
            <div class="grid grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost</label>
                    <input type="text" readonly value="₱ {{ number_format($totalCost, 2) }}"
                           class="w-full border bg-gray-50 rounded-md px-3 py-2 text-gray-600" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remaining Budget</label>
                    <input type="text" readonly value="₱ {{ number_format($remainingBudget, 2) }}"
                           class="w-full border bg-gray-50 rounded-md px-3 py-2 text-gray-600" />
                </div>
            </div>
            @error('allocatedQuantity')
    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
@enderror


            <!-- Buttons -->
            <div class="text-right mt-4">
                <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition"
                        wire:click="saveAllocation">
                    Allocate
                </button>
                <button class="btn btn-gray"
                        wire:click="closeAllocationModal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

@if($showBudgetModal)
<div class="resources-modal">
    <div class="resources-modal-box">
        <div class="resources-modal-header">
            <h3>{{ $editingBudget['budget_id'] ? 'Edit Budget' : 'Add Budget' }}</h3>
            <button class="resources-close-btn" wire:click="$set('showBudgetModal', false)">&times;</button>
        </div>

        <div class="resources-form-grid">
            <label>
                Estimated Cost
                <input type="number" min="0" step="0.01" wire:model.defer="editingBudget.estimated_cost">
            </label>

            @error('editingBudget.estimated_cost')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="resources-modal-actions">
            <button class="resources-btn-gray" wire:click="$set('showBudgetModal', false)">Cancel</button>
            <button class="btn btn-success px-3 py-1 rounded-md text-white hover:bg-green-600 transition"
                    wire:click="{{ $editingBudget['budget_id'] ? 'saveBudget' : 'addBudget' }}">
                {{ $editingBudget['budget_id'] ? 'Update' : 'Add' }}
            </button>
        </div>
    </div>
</div>
@endif




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
