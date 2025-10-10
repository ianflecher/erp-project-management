<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;



new #[Layout('components.layouts.app')] class extends Component
{
    // Projects
    public array $projects = [];
    public array $employees = [];
    public ?string $successMessage = null;
    public bool $showMemberModal = false;
    public ?int $project_member_id = null;
    public array $phaseMembers = [];
    
public array $ganttTasks = []; // tasks for the Gantt chart

    public $projectMembers = [];
    public $selectedProjectId;
    public $selectedMemberId;
    public array $managers = [];
    


    // Modals: project, phase, task, view
    public bool $showProjectModal = false;
    public bool $showPhaseModal = false;
    public bool $showTaskModal = false;
    public bool $showViewPhasesModal = false;
    public bool $showViewTasksModal = false;
    public bool $showEditTaskModal = false;

    // Errors
    public ?string $taskError = null;
    public ?string $phaseError = null;

    // Project fields
    public string $project_name = '';
    public string $description = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $status = 'Planned';
    public float $budget_total = 0.0;
    public int $project_manager_id = 1;

    // Phase fields
    public int $currentProjectId = 0; // used when opening phases modal / adding phase
    public int $viewPhasesProjectId = 0;
    public array $viewPhases = [];

    public string $phase_name = '';
    public string $phase_description = '';
    public string $phase_start_date = '';
    public string $phase_end_date = '';
    public string $phase_status = 'Planned';
    

    // Tasks
    public int $currentPhaseId = 0; // used when opening task modal / adding task
    public int $viewTasksPhaseId = 0;
    public array $viewTasks = [];

    public string $task_name = '';
    public string $task_description = '';
    public string $task_start_date = '';
    public string $task_end_date = '';
    public ?int $task_dependency = null;
    public ?int $task_assigned_to = null;
    public string $task_status = 'Planned';

    // Edit task
    public int $editTaskId = 0;
    public string $editTaskName = '';
    public string $editTaskStartDate = '';
    public string $editTaskEndDate = '';
    public string $editTaskStatus = 'Planned';
    public float $editTaskProgress = 0.0;
    public ?int $editTaskDependency = null;
    public ?int $editTaskAssignedTo = null;
    public ?string $editTaskDescription = null;
   public $ganttProjectId;

    // Mount
    public function mount()
{
    $this->loadProjects();
    $this->loadEmployees(); // load non-managers into $this->employees
    $this->loadManagers();  // load managers into $this->managers
}

public function loadEmployees()
{
    $this->employees = DB::table('hr_employees')
        ->where('role', 'NOT LIKE', '%Manager%')
        ->orderBy('full_name')
        ->get()
        ->toArray();
}

public function loadManagers()
{
    $this->managers = DB::table('hr_employees')
        ->where('role', 'LIKE', '%Manager%')
        ->orderBy('full_name')
        ->get()
        ->toArray();
}






    public function openMemberModal($projectId)
{
    $this->selectedProjectId = $projectId;

    // Assuming project_member_id stores comma-separated employee IDs
    $project = DB::table('projects')->where('project_id', $projectId)->first();
    $this->projectMembers = $project && $project->project_member_id
        ? DB::table('hr_employees')->whereIn('employee_id', explode(',', $project->project_member_id))->get()
        : collect();

    $this->showMemberModal = true;
}

public function closeMemberModal()
{
    $this->showMemberModal = false;
    $this->selectedMemberId = null;
}

public function addMember()
{
    if (!$this->selectedMemberId) return;

    $project = DB::table('projects')->where('project_id', $this->selectedProjectId)->first();
    $members = $project->project_member_id ? explode(',', $project->project_member_id) : [];
    if(!in_array($this->selectedMemberId, $members)) {
        $members[] = $this->selectedMemberId;
    }

    DB::table('projects')->where('project_id', $this->selectedProjectId)->update([
        'project_member_id' => implode(',', $members),
        'updated_at' => now(),
    ]);

    $this->openMemberModal($this->selectedProjectId); // refresh members
}

public function removeMember($employeeId)
{
    $project = DB::table('projects')->where('project_id', $this->selectedProjectId)->first();
    $members = $project->project_member_id ? explode(',', $project->project_member_id) : [];
    $members = array_filter($members, fn($id) => $id != $employeeId);

    DB::table('projects')->where('project_id', $this->selectedProjectId)->update([
        'project_member_id' => implode(',', $members),
        'updated_at' => now(),
    ]);

    $this->openMemberModal($this->selectedProjectId); // refresh members
}

    /***************
     * Project CRUD
     ***************/
    public function loadProjects()
    {
        // Load projects and manager name, plus a basic progress value stored in DB is optional.
        $this->projects = DB::table('projects')
            ->leftJoin('hr_employees', 'projects.project_manager_id', '=', 'hr_employees.employee_id')
            ->select('projects.*', 'hr_employees.full_name as manager_name')
            ->orderBy('projects.start_date', 'desc')
            ->get()
            ->toArray();
    }

    public function openProjectModal()
    {
        $this->resetProjectFields();
        $this->showProjectModal = true;
    }

    public function closeProjectModal()
    {
        $this->showProjectModal = false;
    }

    public function saveProject()
    {
        if (!$this->project_name || !$this->start_date || !$this->end_date) return;

        DB::table('projects')->insert([
            'project_name'       => $this->project_name,
            'description'        => $this->description,
            'start_date'         => $this->start_date,
            'end_date'           => $this->end_date,
            'status'             => $this->status,
            'budget_total'       => $this->budget_total,
            'project_manager_id' => $this->project_manager_id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->loadProjects();
        $this->closeProjectModal();
    }

    public function resetProjectFields()
    {
        $this->project_name = '';
        $this->description = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->status = 'Planned';
        $this->budget_total = 0.0;
        $this->project_manager_id = 1;
    }

    public function updateProjectStatus(int $projectId, string $newStatus)
    {
        if ($newStatus === 'Done' && !$this->canMarkProjectDone($projectId)) {
            $this->taskError = 'Cannot mark project as Done until all phases and tasks are completed.';
            return;
        }

        DB::table('projects')->where('project_id', $projectId)->update([
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

        $this->loadProjects();
        $this->taskError = null;
    }

    private function canMarkProjectDone(int $projectId): bool
    {
        // fetch tasks via phases
        $tasks = DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->where('project_phases.project_id', $projectId)
            ->select('tasks.*')
            ->get();

        // if there are no tasks, require no tasks? We'll allow marking Done if no tasks exist.
        if ($tasks->count() === 0) {
            // but require all phases to be Done as well
            $phases = DB::table('project_phases')
                ->where('project_id', $projectId)
                ->get();

            foreach ($phases as $phase) {
                if (!isset($phase->status) || trim($phase->status) !== 'Done') {
                    return false;
                }
            }
            return true;
        }

        foreach ($tasks as $task) {
            if (!isset($task->status) || trim($task->status) !== 'Done') {
                return false;
            }
        }

        return true;
    }

    /*****************
     * Phase CRUD
     *****************/

    
    
    public function openViewPhasesModal(int $projectId)
    {
        $this->viewPhasesProjectId = $projectId;
        $this->viewPhases = DB::table('project_phases')
            ->where('project_id', $projectId)
            ->orderBy('start_date', 'asc')
            ->get()
            ->toArray();
            $this->closeViewPhasesModal();
        $this->showViewPhasesModal = true;
    }

    public function closeViewPhasesModal()
    {
        $this->showViewPhasesModal = false;
    }

    public function openPhaseModal(int $projectId)
    {
        $this->currentProjectId = $projectId;
        $this->showViewPhasesModal = false;
        $this->resetPhaseFields();
        $this->showPhaseModal = true;
    }

    public function closePhaseModal()
    {
        $this->showPhaseModal = false;
    }

    public function savePhase()
    {
        if (!$this->phase_name || !$this->phase_start_date || !$this->phase_end_date) return;

        DB::table('project_phases')->insert([
            'project_id'   => $this->currentProjectId,
            'phase_name'   => $this->phase_name,
            'description'  => $this->phase_description,
            'start_date'   => $this->phase_start_date,
            'end_date'     => $this->phase_end_date,
            'status'       => 'Planned',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // refresh phases view if open for same project
        if ($this->showViewPhasesModal && $this->viewPhasesProjectId === $this->currentProjectId) {
            $this->openViewPhasesModal($this->currentProjectId);
        }

        // Update project progress (may remain planned)
        $this->updateProjectProgress($this->currentProjectId);

        $this->closePhaseModal();
    }

    public function resetPhaseFields()
    {
        $this->phase_name = '';
        $this->phase_description = '';
        $this->phase_start_date = '';
        $this->phase_end_date = '';
        $this->phase_status = 'Planned';
    }

    /*****************
     * Task CRUD (under Phase)
     *****************/
    public function openTaskModal(int $phaseId){
    $this->closeViewPhasesModal();
    $this->currentPhaseId = $phaseId;
    $this->resetTaskFields();
    

    // Get the phase
    $phase = DB::table('project_phases')->where('phase_id', $phaseId)->first();
    if ($phase) {
        // Get project members for this phase's project
        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
        if ($project && $project->project_member_id) {
            $memberIds = explode(',', $project->project_member_id);
            $this->phaseMembers = DB::table('hr_employees')
                ->whereIn('employee_id', $memberIds)
                ->orderBy('full_name')
                ->get()
                ->toArray();
        } else {
            $this->phaseMembers = [];
        }
    }

    $this->showTaskModal = true;
}

    public function closeTaskModal()
    {
        $this->showTaskModal = false;
    }

    public function saveTask()
    {
        if (!$this->task_name || !$this->task_start_date || !$this->task_end_date) return;

        // Validate dependency if provided: must be in same phase's project (we allow dependency across same phase)
        if ($this->task_dependency !== null) {
            $exists = DB::table('tasks')
                ->where('task_id', $this->task_dependency)
                ->where('phase_id', $this->currentPhaseId)
                ->exists();

            if (!$exists) {
                $this->taskError = 'Invalid dependency task ID. It must be an existing task in this phase or empty.';
                return;
            }
        }

        $this->taskError = null;

        DB::table('tasks')->insert([
            'phase_id' => $this->currentPhaseId,
            'task_name' => $this->task_name,
            'description' => $this->task_description,
            'start_date' => $this->task_start_date,
            'end_date' => $this->task_end_date,
            'dependency_task_id' => $this->task_dependency,
            'assigned_to' => $this->task_assigned_to,
            'status' => 'Planned',
            'progress_percentage' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update phase & project progress
        $phase = DB::table('project_phases')->where('phase_id', $this->currentPhaseId)->first();
        if ($phase) {
            $this->updatePhaseProgress($this->currentPhaseId);
            $this->updateProjectProgress($phase->project_id);
        }

        // Refresh tasks view if open for same phase
        if ($this->showViewTasksModal && $this->viewTasksPhaseId === $this->currentPhaseId) {
            $this->openViewTasksModal($this->currentPhaseId);
        }

        $this->closeTaskModal();
    }

    public function resetTaskFields()
    {
        $this->task_name = '';
        $this->task_description = '';
        $this->task_start_date = '';
        $this->task_end_date = '';
        $this->task_dependency = null;
        $this->task_assigned_to = null;
        $this->task_status = 'Planned';
    }

    public function openViewTasksModal(int $phaseId)
    {
        $this->viewTasksPhaseId = $phaseId;

        $this->viewTasks = DB::table('tasks')
            ->where('phase_id', $phaseId)
            ->orderBy('start_date', 'asc')
            ->get()
            ->toArray();

        $this->showViewTasksModal = true;
    }

    public function closeViewTasksModal()
    {
        $this->showViewTasksModal = false;
    }

    public function editTask($taskId)
{
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task) return;

    $this->editTaskId = $task->task_id;
    $this->editTaskName = $task->task_name;
    $this->editTaskStartDate = $task->start_date;
    $this->editTaskEndDate = $task->end_date;
    $this->editTaskStatus = $task->status;
    $this->editTaskProgress = (float) $task->progress_percentage;
    $this->editTaskDependency = $task->dependency_task_id;
    $this->editTaskAssignedTo = $task->assigned_to;
    $this->editTaskDescription = $task->description;

    // Load project members for this task's phase
    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    if ($phase) {
        $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
        if ($project && $project->project_member_id) {
            $memberIds = explode(',', $project->project_member_id);
            $this->phaseMembers = DB::table('hr_employees')
                ->whereIn('employee_id', $memberIds)
                ->orderBy('full_name')
                ->get()
                ->toArray();
        } else {
            $this->phaseMembers = [];
        }
    }

    $this->showEditTaskModal = true;
}


    public function closeEditTaskModal()
    {
        $this->showEditTaskModal = false;
    }

    public function updateTask()
    {
        if (!$this->editTaskName || !$this->editTaskStartDate || !$this->editTaskEndDate) return;

        $task = DB::table('tasks')->where('task_id', $this->editTaskId)->first();
        if (!$task) return;

        $phaseId = $task->phase_id;
        $phase = DB::table('project_phases')->where('phase_id', $phaseId)->first();
        $projectId = $phase ? $phase->project_id : null;

        // Validate dependency (must belong to same phase)
        if ($this->editTaskDependency !== null) {
            $exists = DB::table('tasks')
                ->where('task_id', $this->editTaskDependency)
                ->where('phase_id', $phaseId)
                ->exists();

            if (!$exists) {
                $this->taskError = 'Invalid dependency task ID. It must exist in this phase.';
                return;
            }
        }

        $this->taskError = null;

        // Auto progress based on status if not explicitly set
        switch ($this->editTaskStatus) {
            case 'Planned':
                $progress = 0;
                break;
            case 'In Progress':
                $progress = 50;
                break;
            case 'Done':
                $progress = 100;
                break;
            default:
                $progress = $this->editTaskProgress ?? 0;
        }

        DB::table('tasks')->where('task_id', $this->editTaskId)->update([
            'task_name' => $this->editTaskName,
            'description' => $this->editTaskDescription,
            'start_date' => $this->editTaskStartDate,
            'end_date' => $this->editTaskEndDate,
            'status' => $this->editTaskStatus,
            'progress_percentage' => $progress,
            'dependency_task_id' => $this->editTaskDependency,
            'assigned_to' => $this->editTaskAssignedTo,
            'updated_at' => now(),
        ]);

        // Update phase & project
        $this->updatePhaseProgress($phaseId);
        if ($projectId) $this->updateProjectProgress($projectId);

        // Refresh tasks modal if open
        if ($this->showViewTasksModal && $this->viewTasksPhaseId === $phaseId) {
            $this->openViewTasksModal($phaseId);
        }

        $this->closeEditTaskModal();
    }

    public function cycleTaskStatus(int $taskId)
{
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task) return;

    // Determine next status & progress
    switch ($task->status) {
        case 'Planned':
            $newStatus = 'In Progress';
            $newProgress = 50;
            break;
        case 'In Progress':
            $newStatus = 'Done';
            $newProgress = 100;
            break;
        default:
            $newStatus = 'Planned';
            $newProgress = 0;
    }

    // Update task
    DB::table('tasks')->where('task_id', $taskId)->update([
        'status' => $newStatus,
        'progress_percentage' => $newProgress,
        'updated_at' => now(),
    ]);

    // Update phase & project progress
    $this->updatePhaseProgress($task->phase_id);

    $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
    if ($phase) {
        $this->updateProjectProgress($phase->project_id);
    }

    // Close modals & show alert only if Done
   if ($newStatus === 'Done') {
    $this->showEditTaskModal   = false;
    $this->showTaskModal       = false;
    $this->showViewTasksModal  = false;
    $this->showPhaseModal      = false;
    $this->showViewPhasesModal = false;
    $this->showProjectModal    = false;

    $this->successMessage = "✅ Task '{$task->task_name}' marked as Done.";
}


    // Refresh tasks view if modal is open
    if ($this->showViewTasksModal) {
        $this->openViewTasksModal($task->phase_id);
    }

    // Refresh project list
    $this->loadProjects();
}



    public function markTaskCompleted($task_id)
{
    DB::table('tasks')
        ->where('task_id', $task_id)
        ->update([
            'status' => 'Completed',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

    // Update the phase and project automatically
    $task = DB::table('tasks')->where('task_id', $task_id)->first();
    if ($task && $task->phase_id) {
        $this->updatePhaseProgress($task->phase_id);
    }

    $this->refreshData();
}

private function refreshData()
{
    $this->projects = DB::table('projects')
        ->orderBy('start_date', 'desc')
        ->get()
        ->toArray();
}

    public function markTaskDone(int $taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task) return;

        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'Done',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

        // Refresh tasks if modal is open
        if ($this->showViewTasksModal) {
            $this->openViewTasksModal($task->phase_id);
        }

        $phase = DB::table('project_phases')->where('phase_id', $task->phase_id)->first();
        if ($phase) {
            $this->updatePhaseProgress($task->phase_id);
            $this->updateProjectProgress($phase->project_id);
        }
    }

    /**********************
     * Progress / Status
     **********************/
    private function updatePhaseProgress($phase_id)
{
    $tasks = DB::table('tasks')
        ->where('phase_id', $phase_id)
        ->get();

    if ($tasks->count() === 0) return;

    $completedTasks = $tasks->whereIn('status', ['Done', 'Completed'])->count();

    DB::table('project_phases')
        ->where('phase_id', $phase_id)
        ->update([
            'status' => $completedTasks === $tasks->count() ? 'Done' : 'In Progress',
            'updated_at' => now(),
        ]);

    $this->updateProjectProgressByPhase($phase_id);
}

private function updateProjectProgressByPhase($phase_id)
{
    $phase = DB::table('project_phases')->where('phase_id', $phase_id)->first();
    if (!$phase) return;

    $phases = DB::table('project_phases')->where('project_id', $phase->project_id)->get();

    $totalPhases = $phases->count();
    $completedPhases = $phases->whereIn('status', ['Done', 'Completed'])->count();

    $status = $completedPhases === $totalPhases
        ? 'Completed'
        : ($completedPhases > 0 ? 'In Progress' : 'Planned');

    DB::table('projects')
        ->where('project_id', $phase->project_id)
        ->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
}



    private function updateProjectProgress(int $projectId)
    {
        // compute project progress from its phases (average of phase progress computed from tasks)
        $phases = DB::table('project_phases')->where('project_id', $projectId)->get();

        if ($phases->count() === 0) {
            // no phases -> keep project Planned
            DB::table('projects')->where('project_id', $projectId)->update([
                'status' => 'Planned',
                'updated_at' => now(),
            ]);
            $this->loadProjects();
            return;
        }

        $phaseProgressVals = [];
        foreach ($phases as $phase) {
            $tasks = DB::table('tasks')->where('phase_id', $phase->phase_id)->get();
            if ($tasks->count() === 0) {
                $phaseProgressVals[] = 0;
            } else {
                $phaseProgressVals[] = (float) $tasks->avg('progress_percentage');
            }
        }

        $projectAvg = count($phaseProgressVals) ? array_sum($phaseProgressVals) / count($phaseProgressVals) : 0;

        $allPhasesDone = collect($phases)->every(fn($ph) => isset($ph->status) && trim($ph->status) === 'Done');

        DB::table('projects')->where('project_id', $projectId)->update([
            'status' => $allPhasesDone ? 'Completed' : ($projectAvg > 0 ? 'In Progress' : 'Planned'),
            'updated_at' => now(),
        ]);

        $this->loadProjects();
    }
};
?>

<div>
    
@if ($successMessage)
    <div 
        x-data="{ show: true }"
        x-show="show"
        x-transition
        x-init="setTimeout(() => {
            show = false;
            @this.set('successMessage', null)
        }, 3000)" 
        class="mb-4 px-4 py-3 rounded-lg bg-green-100 border border-green-300 text-green-800 shadow transition-all duration-500"
    >
        {{ $successMessage }}
    </div>
@endif


    <!-- Add Project Button -->
    <button type="button" wire:click="openProjectModal" class="btn btn-primary" style="margin-bottom:1rem;">+ Add Project</button>

    <!-- Projects Table -->
   <table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Start</th>
            <th>End</th>
            <th>Status</th>
            <th>Budget</th>
            <th>Manager</th>
            <th>Member</th>
            <th>Phases</th>
            <th>Gantt</th> <!-- New Column -->
        </tr>
    </thead>
    <tbody>
        @foreach ($projects as $p)
            <tr>
                <td>{{ $p->project_name }}</td>
                <td>{{ $p->description }}</td>
                <td>{{ $p->start_date }}</td>
                <td>{{ $p->end_date }}</td>
                <td>{{ $p->status }}</td>
                <td>{{ number_format($p->budget_total, 2) }}</td>
                <td>{{ $p->manager_name ?? 'Unknown' }}</td>
                <td>
                    <!-- View Member Button -->
                    <button type="button" 
                        wire:click="openMemberModal({{ $p->project_id }})"
                        style="
                background-color: #218838;
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 0.9rem;
                font-weight: 500;
                line-height: 1.2;
                white-space: nowrap;
                cursor: pointer;
                transition: background-color 0.2s ease;
            ">
                        View
                    </button>
                </td>
                <td>
    <div style="display: flex; gap: 0.5rem; align-items: center;">

        <!-- View Phases Button -->
        <button type="button" 
            wire:click="openViewPhasesModal({{ $p->project_id }})"
            style="
                background-color: #218838;
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 0.9rem;
                font-weight: 500;
                line-height: 1.2;
                white-space: nowrap;
                cursor: pointer;
                transition: background-color 0.2s ease;
            ">
            View
        </button>
    </div>
</td>
                <td>
                    <!-- New View Gantt Button -->
                    <button type="button"
                       onclick="loadGantt({{ $p->project_id }})"
                        style="
                background-color: #218838;
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 8px 14px;
                font-size: 0.9rem;
                font-weight: 500;
                line-height: 1.2;
                white-space: nowrap;
                cursor: pointer;
                transition: background-color 0.2s ease;
            ">
                        View
                    </button>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div id="gantt_here" style="width:100%; height:500px;"></div>


    <!-- Project Modal -->
    <div class="modal" style="display: {{ $showProjectModal ? 'flex' : 'none' }};">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>New Project</div>
                <button type="button" wire:click="closeProjectModal" class="btn btn-warning">Close</button>
            </div>
            <div class="modal-body">
                <form class="form-grid" wire:submit.prevent="saveProject">
                    <label>Project Name:<input type="text" wire:model="project_name" required /></label>
                    <label>Start Date:<input type="date" wire:model="start_date" required /></label>
                    <label>End Date:<input type="date" wire:model="end_date" required /></label>
                    <label>Description:<input type="text" wire:model="description" /></label>
                    <label>Status:
                        <select wire:model="status" required>
                            <option value="Planned">Planned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </label>

                    <label>Budget:<input type="number" wire:model="budget_total" min="0" step="0.01" /></label>
                    <label>Project Manager:
    <select wire:model="project_manager_id" required>
    <option value="">-- Select Manager --</option>
    @foreach ($managers as $manager)
        <option value="{{ $manager->employee_id }}">{{ $manager->full_name }}</option>
    @endforeach
</select>

</label>


                    <button type="submit" class="btn btn-primary">Save Project</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Phase Modal (Add Phase) -->
    <div class="modal" style="display: {{ $showPhaseModal ? 'flex' : 'none' }};">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>New Phase for Project #{{ $currentProjectId }}</div>
                <button type="button" wire:click="closePhaseModal" class="btn btn-warning">Close</button>
            </div>
            <div class="modal-body">
                @if($phaseError)
                    <p style="color:red; margin-bottom:0.5rem;">{{ $phaseError }}</p>
                @endif
                <form class="form-grid" wire:submit.prevent="savePhase">
                    <label>Phase Name:<input type="text" wire:model="phase_name" required /></label>
                    <label>Start Date:<input type="date" wire:model="phase_start_date" required /></label>
                    <label>End Date:<input type="date" wire:model="phase_end_date" required /></label>
                    <label>Description:<input type="text" wire:model="phase_description" /></label>
                    <button type="submit" class="btn btn-primary">Save Phase</button>
                </form>
            </div>
        </div>
    </div>

    <!-- View Phases Modal -->
    <div class="modal" style="display: {{ $showViewPhasesModal ? 'flex' : 'none' }}; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div class="modal-dialog" style="background:#fff; border-radius:8px; width:90%; max-width:700px; overflow:hidden;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border-bottom:1px solid #ddd;">
            <div>Phases for Project #{{ $viewPhasesProjectId }}</div>
            <button type="button" wire:click="closeViewPhasesModal" style="background:#28a745; color:#fff; border:none; padding:0.4rem 0.8rem; border-radius:4px; cursor:pointer;">Close</button>
        </div>

        <div class="modal-body" style="padding:1rem; max-height:500px; overflow-y:auto;">

            <!-- Fixed Add Phase Button -->
            <div style="margin-bottom:0.75rem; text-align:right;">
                <button type="button" wire:click="openPhaseModal({{ $viewPhasesProjectId }})" 
                        style="background:#28a745; color:#fff; border:none; border-radius:6px; padding:0.4rem 0.8rem; font-size:0.85rem; cursor:pointer;">
                    + Add Phase
                </button>
            </div>

            @if(count($viewPhases))
                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Phase Name</th>
                            <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Start</th>
                            <th style="border-bottom:1px solid #ccc; padding:0.5rem;">End</th>
                            <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Status</th>
                            <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($viewPhases as $ph)
                            <tr>
                                <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $ph->phase_name }}</td>
                                <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $ph->start_date }}</td>
                                <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $ph->end_date }}</td>
                                <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $ph->status }}</td>
                                <td style="border-bottom:1px solid #eee; padding:0.5rem; display:flex; gap:0.25rem;">
                                    <button type="button" wire:click="openTaskModal({{ $ph->phase_id }})" 
                                            style="background:#28a745; color:#fff; border:none; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.75rem; cursor:pointer;">
                                        + Task
                                    </button>
                                    <button type="button" wire:click="openViewTasksModal({{ $ph->phase_id }})" 
                                            style="background:#28a745; color:#fff; border:none; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.75rem; cursor:pointer;">
                                        View
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="text-align:center; color:#6c757d;">No phases found for this project.</p>
            @endif
        </div>
    </div>
</div>


    <!-- Task Modal (Add) -->
    <div class="modal" style="display: {{ $showTaskModal ? 'flex' : 'none' }};">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>New Task (Phase #{{ $currentPhaseId }})</div>
                <button type="button" wire:click="closeTaskModal" class="btn btn-warning">Close</button>
            </div>
            <div class="modal-body">
                @if($taskError)
                    <p style="color:red; margin-bottom:0.5rem;">{{ $taskError }}</p>
                @endif
                <form class="form-grid" wire:submit.prevent="saveTask">
                    <label>Task Name:<input type="text" wire:model="task_name" required /></label>
                    <label>Start Date:<input type="date" wire:model="task_start_date" required /></label>
                    <label>End Date:<input type="date" wire:model="task_end_date" required /></label>
                    <label>Description:<input type="text" wire:model="task_description" /></label>
                    <label>Dependency Task ID:<input type="number" wire:model="task_dependency" min="1" /></label>
                    <label>Assign To:
                            
                        </select><select wire:model="task_assigned_to">
    <option value="">-- Select Member --</option>
    @foreach ($phaseMembers as $member)
        <option value="{{ $member->employee_id }}">{{ $member->full_name }}</option>
    @endforeach
</select>

                    </label>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </form>
            </div>
        </div>
    </div>

    <!-- View Tasks Modal (per Phase) -->
    <div class="modal" style="display: {{ $showViewTasksModal ? 'flex' : 'none' }};">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>Tasks for Phase #{{ $viewTasksPhaseId }}</div>
                <button type="button" wire:click="closeViewTasksModal" class="btn btn-warning">Close</button>
            </div>
            <div class="modal-body">
                @if(count($viewTasks))
                    <table style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Task Name</th>
                                <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Start Date</th>
                                <th style="border-bottom:1px solid #ccc; padding:0.5rem;">End Date</th>
                                <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Status</th>
                                <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Progress</th>
                                <th style="border-bottom:1px solid #ccc; padding:0.5rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($viewTasks as $t)
                                <tr>
                                    <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $t->task_name }}</td>
                                    <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $t->start_date }}</td>
                                    <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $t->end_date }}</td>
                                    <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $t->status }}</td>
                                    <td style="border-bottom:1px solid #eee; padding:0.5rem;">{{ $t->progress_percentage }}%</td>
                                    <td style="border-bottom:1px solid #eee; padding:0.5rem; display:flex; gap:0.25rem;">
                                        @if($t->status === 'Done')
                                            <button type="button" class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.75rem;" disabled>Already Done</button>
                                        @else
                                            <button type="button" wire:click="cycleTaskStatus({{ $t->task_id }})" class="btn btn-primary" style="padding:0.25rem 0.5rem; font-size:0.75rem;">Change Status</button>
                                        @endif

                                        <button type="button" wire:click="editTask({{ $t->task_id }})" class="btn btn-success" style="padding:0.25rem 0.5rem; font-size:0.75rem;">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p>No tasks found for this phase.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div class="modal" style="display: {{ $showEditTaskModal ? 'flex' : 'none' }};">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>Edit Task #{{ $editTaskId }}</div>
                <button type="button" wire:click="closeEditTaskModal" class="btn btn-warning">Close</button>
            </div>
            <div class="modal-body">
                @if($taskError)
                    <p style="color:red; margin-bottom:0.5rem;">{{ $taskError }}</p>
                @endif
                <form class="form-grid" wire:submit.prevent="updateTask">
                    <label>Task Name: <input type="text" wire:model="editTaskName" required /></label>
                    <label>Start Date: <input type="date" wire:model="editTaskStartDate" required /></label>
                    <label>End Date: <input type="date" wire:model="editTaskEndDate" required /></label>
                    <label>Status:
                        <select wire:model="editTaskStatus">
                            <option value="Planned">Planned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Done">Done</option>
                        </select>
                    </label>
                    <label>Dependency Task ID: <input type="number" wire:model="editTaskDependency" min="1" /></label>
                    <label>Assign To:
                        <select wire:model="editTaskAssignedTo">
    <option value="">-- Select Member --</option>
    @foreach ($phaseMembers as $member)
        <option value="{{ $member->employee_id }}">{{ $member->full_name }}</option>
    @endforeach
</select>

                    </label>
                    <label>Description: <input type="text" wire:model="editTaskDescription" /></label>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" style="display: {{ $showMemberModal ? 'flex' : 'none' }};">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>Add Project Member</div>
            <button type="button" wire:click="closeMemberModal" class="btn btn-warning">Close</button>
        </div>
        <div class="modal-body">
            <form wire:submit.prevent="saveMember">
                <label>Member:
                    <select wire:model="project_member_id" required>
                        <option value="">-- Select Member --</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->employee_id }}">{{ $emp->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">Save Member</button>
            </form>
        </div>
    </div>
</div>
<div class="modal fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50" style="display: {{ $showMemberModal ? 'flex' : 'none' }};">
    <div class="modal-dialog bg-white rounded-lg shadow-lg w-full max-w-lg p-6">
        <!-- Header -->
        <div class="modal-header flex justify-between items-center mb-4 border-b border-gray-200 pb-2">
            <div class="text-xl font-semibold text-gray-800">Project Members</div>
            <button type="button" wire:click="closeMemberModal" class="btn btn-warning px-3 py-1 rounded-md">Close</button>
        </div>

        <!-- Members List -->
        <div class="modal-body space-y-3 max-h-80 overflow-y-auto">
            @forelse ($projectMembers as $member)
                <div class="flex justify-between items-center bg-green-50 p-3 rounded-md shadow-sm hover:shadow-md transition">
                    <div>
                        <div class="font-medium text-gray-800">{{ $member->full_name }}</div>
                        <div class="text-sm text-gray-600 px-2 py-1 bg-green-200 rounded-full inline-block">{{ $member->role }}</div>
                    </div>
                    <button type="button" wire:click="removeMember({{ $member->employee_id }})" class="btn btn-danger px-3 py-1 rounded-md">
                        Remove
                    </button>
                </div>
            @empty
                <div class="text-center text-gray-400 italic">No members yet.</div>
            @endforelse
        </div>

        <!-- Add Member Section -->
        <form class="form-grid mt-4" wire:submit.prevent="addMember">
            <label class="flex flex-col">
                Select Employee:
                <select wire:model="selectedMemberId" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400">
                    <option value="">-- Select Employee --</option>
                    @foreach ($employees as $emp)
    @if(!collect($projectMembers)->pluck('employee_id')->contains($emp->employee_id))
        <option value="{{ $emp->employee_id }}">{{ $emp->full_name }}</option>
    @endif
@endforeach

                </select>
            </label>
            <button type="submit" class="btn btn-primary">Add Member</button>
        </form>
    </div>
    
</div>


<!-- Include DHTMLX Gantt -->
<script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
<link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css">
<script>
    function loadGantt(projectId) {
    console.log("Loading Gantt for project:", projectId);

    fetch(`/gantt-tasks/${projectId}`)
        .then(res => res.json())
        .then(tasks => {
            const ganttData = {
                data: tasks.map(t => ({
                    ...t,
                    duration: Math.ceil((new Date(t.end_date) - new Date(t.start_date)) / (1000*60*60*24)) + 1
                }))
            };

            gantt.init("gantt_here");
            gantt.parse(ganttData);
        })
        .catch(err => console.error("Error fetching Gantt tasks:", err));
}

</script>