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
    public $projectToDelete = null;
    public $editingPhase = [];
    public $showEditPhaseModal = false;
    public $phases = [];
    

public array $ganttTasks = []; // tasks for the Gantt chart

    public $selectedProjectId;
    public array $managers = [];
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public $editProject = [];
    public $deleteId = null;

    // Modals: project, phase, task, view
    public bool $showProjectModal = false;
    public bool $showPhaseModal = false;
    public bool $showTaskModal = false;
    public bool $showViewPhasesModal = false;
    public bool $showViewTasksModal = false;
    public bool $showEditTaskModal = false;
    public $editingProject = null;

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
    
    public string $task_status = 'Planned';

    // Edit task
    public int $editTaskId = 0;
    public string $editTaskName = '';
    public string $editTaskStartDate = '';
    public string $editTaskEndDate = '';
    public string $editTaskStatus = 'Planned';
    public float $editTaskProgress = 0.0;
    public ?int $editTaskDependency = null;
    
    public ?string $editTaskDescription = null;
   public $ganttProjectId;

    // Mount
    public function mount()
{
    $this->loadProjects();
    $this->loadEmployees(); // load non-managers into $this->employees
    $this->loadManagers();  // load managers into $this->managers
}

    public function deleteTask($taskId)
{
    if (!$taskId) return;

    DB::table('tasks')->where('task_id', $taskId)->delete();

    // Refresh tasks for the current phase
    $this->viewTasks = DB::table('tasks')
        ->where('phase_id', $this->viewTasksPhaseId)
        ->get()
        ->toArray();
}


    public function openEditPhaseModal($phaseId)
{
    // Close the view phases modal first
    $this->showViewPhasesModal = false;

    $phase = DB::table('project_phases')->where('phase_id', $phaseId)->first();

    // Convert object to array so Livewire can bind inputs
    $this->editingPhase = [
        'phase_id' => $phase->phase_id,
        'phase_name' => $phase->phase_name,
        'start_date' => $phase->start_date,
        'end_date' => $phase->end_date,
        'status' => $phase->status,
    ];

    $this->showEditPhaseModal = true;
}


// Update phase (call from a modal form)
public function updatePhase()
{
    if (empty($this->editingPhase['phase_id'])) return;

    DB::table('project_phases')->where('phase_id', $this->editingPhase['phase_id'])->update([
        'phase_name' => $this->editingPhase['phase_name'],
        'start_date' => $this->editingPhase['start_date'],
        'end_date' => $this->editingPhase['end_date'],
        'status' => $this->editingPhase['status'],
        'updated_at' => now(),
    ]);

    $this->showEditPhaseModal = false;
    $this->editingPhase = [];

    $this->loadProjects(); // refresh phases after update
}


// Delete a phase
public function deletePhase($phaseId)
{
    if (!$phaseId) return;

    // Delete the phase
    DB::table('project_phases')->where('phase_id', $phaseId)->delete();

    // Refresh the list of phases for the current project
    $this->viewPhases = DB::table('project_phases')
        ->where('project_id', $this->viewPhasesProjectId)
        ->get()
        ->toArray();
}


public function openEditModal($projectId)
{
    $project = DB::table('projects')->where('project_id', $projectId)->first();

    if ($project) {
        $this->editProject = (array) $project; // convert object to array
        $this->showEditModal = true;
    }
}

public function closeEditModal()
{
    $this->showEditModal = false;
    $this->editProject = [];
}

public function updateProject()
{
    if (!isset($this->editProject['project_id'])) {
        return;
    }

    DB::table('projects')
        ->where('project_id', $this->editProject['project_id'])
        ->update([
            'project_name'         => $this->editProject['project_name'] ?? '',
            'description'          => $this->editProject['description'] ?? '',
            'budget_total'         => $this->editProject['budget_total'] ?? 0,
            'project_manager_id'   => $this->editProject['project_manager_id'] ?? null,
            'start_date'           => $this->editProject['start_date'] ?? null,
            'end_date'             => $this->editProject['end_date'] ?? null,
            'updated_at'           => now(),
        ]);

    $this->closeEditModal();
    $this->loadProjects(); // refresh the table
}


public function confirmDelete($projectId)
{
    $this->projectToDelete = $projectId;
    $this->showDeleteModal = true;
}

public function deleteProject()
{
    if ($this->projectToDelete) {
        DB::table('projects')->where('project_id', $this->projectToDelete)->delete();

        $this->showDeleteModal = false;

        // Refresh your projects list
        $this->projects = DB::table('projects')->get()->toArray();

        $this->projectToDelete = null;
    }
}




public function closeDeleteModal()
{
    $this->showDeleteModal = false;
    $this->deleteId = null;
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
        ->where('role', 'NOT LIKE', '%Manager%')
        ->orderBy('full_name')
        ->get()
        ->toArray();
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
            'assigned_to' => null,
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

    public function openViewTasksModal($phaseId)
{
    $this->viewTasksPhaseId = $phaseId;

    // Load tasks for this phase
    $this->viewTasks = DB::table('tasks')
    ->where('phase_id', $phaseId)
    ->get()        // returns Collection
    ->toArray();   // convert to array

    // Load all phases (if not already loaded)
    if (empty($this->phases)) {
        $this->phases = DB::table('project_phases')->get()->toArray();
    }

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
    $this->editTaskAssignedTo = null;
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
            'assigned_to' => null,
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
{{-- ✅ Edit Project Modal --}}
@if ($showEditModal)
    <div class="modal" aria-hidden="{{ $showEditModal ? 'false' : 'true' }}">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="text-lg font-semibold">Edit Project</h3>
                <button wire:click="closeEditModal" class="text-white font-bold text-xl">&times;</button>
            </div>
            <div class="modal-body">
                <label>
                    <span class="font-medium text-gray-700">Project Name</span>
                    <input type="text" wire:model="editProject.project_name" class="w-full border rounded p-2 mt-1">
                </label>

                <label>
                    <span class="font-medium text-gray-700">Description</span>
                    <textarea wire:model="editProject.description" class="w-full border rounded p-2 mt-1"></textarea>
                </label>

                <label>
                    <span class="font-medium text-gray-700">Budget</span>
                    <input type="number" wire:model="editProject.budget_total" class="w-full border rounded p-2 mt-1">
                </label>

                {{-- Project Manager Dropdown --}}
                <label>
                    <span class="font-medium text-gray-700">Project Manager</span>
                    <select wire:model="editProject.project_manager_id" class="w-full border rounded p-2 mt-1">
                        <option value="">-- Select Manager --</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->employee_id }}">{{ $manager->full_name }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- Start Date --}}
                <label>
                    <span class="font-medium text-gray-700">Start Date</span>
                    <input type="date" wire:model="editProject.start_date" class="w-full border rounded p-2 mt-1">
                </label>

                {{-- End Date --}}
                <label>
                    <span class="font-medium text-gray-700">End Date</span>
                    <input type="date" wire:model="editProject.end_date" class="w-full border rounded p-2 mt-1">
                </label>
            </div>
            <div class="modal-footer">
                <button wire:click="closeEditModal" class="btn btn-secondary">Cancel</button>
                <button wire:click="updateProject" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
@endif



{{-- ✅ Delete Confirmation Modal --}}
@if ($showDeleteModal)
    <div class="modal" aria-hidden="{{ $showDeleteModal ? 'false' : 'true' }}">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="text-lg font-semibold">Confirm Delete</h3>
                <button wire:click="closeDeleteModal" class="text-white font-bold text-xl">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this project? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button wire:click="closeDeleteModal" class="btn btn-secondary">Cancel</button>
                <button wire:click="deleteProject" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
@endif


    <!-- Add Project Button -->
    <button type="button" wire:click="openProjectModal" class="btn btn-primary" style="margin-bottom:1rem;">+ Add Project</button>

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
    <thead>
        <!-- Green header bar -->
        <tr>
            <th colspan="11"
                style="background-color:#2e7d32;color:white;text-align:left;
                       padding:10px 12px;font-size:1rem;">
                Projects
            </th>
        </tr>

        <!-- Column headers -->
        <tr style="background-color: #f8f9fa; text-align:left;">
            <th style="padding:8px;">Name</th>
            <th style="padding:8px;">Description</th>
            <th style="padding:8px;">Start</th>
            <th style="padding:8px;">End</th>
            <th style="padding:8px;">Status</th>
            <th style="padding:8px;">Budget</th>
            <th style="padding:8px;">Manager</th>
            <th style="padding:8px;">Member</th>
            <th style="padding:8px;">Phases</th>
            <th style="padding:8px;">Gantt</th>
            <th style="padding:8px; text-align:center;">Actions</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($projects as $p)
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px;">{{ $p->project_name }}</td>
                <td style="padding:8px;">{{ $p->description }}</td>
                <td style="padding:8px;">{{ $p->start_date }}</td>
                <td style="padding:8px;">{{ $p->end_date }}</td>
                <td style="padding:8px;">{{ $p->status }}</td>
                <td style="padding:8px;">₱{{ number_format($p->budget_total, 2) }}</td>
                <td style="padding:8px;">{{ $p->manager_name ?? 'Unknown' }}</td>

                <!-- Member -->
                <td style="padding:8px;">
    <a href="{{ route('projects.members', ['project_id' => $p->project_id]) }}"
       style="background-color:#28a745;color:#fff;border:none;border-radius:5px;
                               padding:4px 8px;font-size:0.8rem;cursor:pointer;">
       View
    </a>
</td>

                <!-- Phases -->
                <td style="padding:8px;">
                    <a href="{{ route('projects.phase', ['project_id' => $p->project_id]) }}"
       style="background-color:#28a745;color:#fff;border:none;border-radius:5px;
                               padding:4px 8px;font-size:0.8rem;cursor:pointer;">
       View
    </a>
                </td>

                <!-- Gantt -->
                <td style="padding:8px;">
                    <button onclick="loadGantt({{ $p->project_id }})"
                        style="background-color:#28a745;color:#fff;border:none;border-radius:5px;
                               padding:4px 8px;font-size:0.8rem;cursor:pointer;">
                        View
                    </button>
                </td>

                <!-- Actions -->
                <td style="padding:8px;text-align:center;">
    <div style="display:flex;justify-content:center;align-items:center;gap:0.3rem;">
        <button wire:click="openEditModal({{ $p->project_id }})"
            style="background-color:#007bff;color:#fff;border:none;border-radius:5px;
                   padding:4px 8px;font-size:0.8rem;cursor:pointer;">
            Edit
        </button>
        <button wire:click="confirmDelete({{ $p->project_id }})"
            style="background-color:#dc3545;color:#fff;border:none;border-radius:5px;
                   padding:4px 8px;font-size:0.8rem;cursor:pointer;">
            Delete
        </button>
    </div>
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

    

    <!-- View Phases Modal -->
    <div class="modal" style="display: {{ $showViewPhasesModal ? 'flex' : 'none' }}; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div class="modal-dialog" style="background:#fff; border-radius:8px; width:90%; max-width:700px; overflow:hidden;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:1rem; border-bottom:1px solid #ddd;">
            <div>
    Phases for Project: 
    {{ collect($projects)->first(fn($p) => $p->project_id == $viewPhasesProjectId)?->project_name ?? 'Unknown Project' }}
</div>

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
                <td style="border-bottom:1px solid #eee; padding:0.5rem; display:flex; gap:0.25rem; flex-wrap:wrap;">
                    <button type="button" wire:click="openTaskModal({{ $ph->phase_id }})" 
                            style="background:#28a745; color:#fff; border:none; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.75rem; cursor:pointer;">
                        + Task
                    </button>
                    <button type="button" wire:click="openViewTasksModal({{ $ph->phase_id }})" 
                            style="background:#28a745; color:#fff; border:none; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.75rem; cursor:pointer;">
                        View
                    </button>
                    <button type="button" 
        wire:click="openEditPhaseModal({{ $ph->phase_id }})" 
        style="background:#ffc107; color:#fff; border:none; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.75rem; cursor:pointer;">
    Edit
</button>

<button type="button" 
        onclick="if(confirm('Are you sure you want to delete this phase?')) { @this.call('deletePhase', {{ $ph->phase_id }}) }" 
        style="background:#dc3545; color:#fff; border:none; border-radius:4px; padding:0.25rem 0.5rem; font-size:0.75rem; cursor:pointer;">
    Delete
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

@if($showEditPhaseModal)
<div class="modal" aria-hidden="false">
    <div class="modal-dialog">
        <!-- Header -->
        <div class="modal-header">
            <h3 class="text-lg font-semibold">Edit Phase</h3>
            <button type="button" wire:click="$set('showEditPhaseModal', false)" style="font-size:1.25rem;font-weight:bold;">&times;</button>
        </div>

        <!-- Body / Form -->
        <div class="modal-body">
            <form wire:submit.prevent="updatePhase" style="display:flex; flex-direction:column; gap:0.75rem;">
                <div style="display:flex; flex-direction:column;">
                    <label class="font-medium">Phase Name</label>
                    <input type="text" wire:model.defer="editingPhase.phase_name" style="border:1px solid #d1d5db; border-radius:0.375rem; padding:0.5rem; width:100%;" />
                </div>

                <div style="display:flex; flex-direction:column;">
                    <label class="font-medium">Start Date</label>
                    <input type="date" wire:model.defer="editingPhase.start_date" style="border:1px solid #d1d5db; border-radius:0.375rem; padding:0.5rem; width:100%;" />
                </div>

                <div style="display:flex; flex-direction:column;">
                    <label class="font-medium">End Date</label>
                    <input type="date" wire:model.defer="editingPhase.end_date" style="border:1px solid #d1d5db; border-radius:0.375rem; padding:0.5rem; width:100%;" />
                </div>

                <div style="display:flex; flex-direction:column;">
                    <label class="font-medium">Status</label>
                    <select wire:model.defer="editingPhase.status" style="border:1px solid #d1d5db; border-radius:0.375rem; padding:0.5rem; width:100%;">
                        <option value="Not Started">Not Started</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <!-- Footer / Buttons -->
                <div class="modal-footer">
                    <button type="button" wire:click="$set('showEditPhaseModal', false)" style="background:#9ca3af;color:#fff;border:none;border-radius:0.25rem;padding:0.5rem 1rem;font-size:0.8rem;cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#2e7d32;color:#fff;border:none;border-radius:0.25rem;padding:0.5rem 1rem;font-size:0.8rem;cursor:pointer;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif


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
                    
                    <label>Description: <input type="text" wire:model="editTaskDescription" /></label>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </form>
            </div>
        </div>
    </div>


<!-- Include DHTMLX Gantt -->
<script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>
<link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css">
<script>
function loadGantt(projectId) {
    fetch(`/gantt-tasks/${projectId}`)
        .then(res => res.json())
        .then(data => {
            // Optional: compute duration if needed
            const ganttData = {
                data: data.map(item => ({
                    ...item,
                    duration: item.start_date && item.end_date ?
                        Math.ceil((new Date(item.end_date) - new Date(item.start_date)) / (1000*60*60*24)) + 1
                        : 0
                }))
            };

            gantt.init("gantt_here");
            gantt.parse(ganttData);
        })
        .catch(err => console.error("Error fetching Gantt data:", err));
}
</script>