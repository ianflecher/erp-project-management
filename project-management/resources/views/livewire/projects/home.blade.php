<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;



new #[Layout('components.layouts.app')] class extends Component
{
    // Projects
    public array $projects = [];
    public ?string $successMessage = null;
    public $projectToDelete = null;
    

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
   public $ganttProjectId;

    // Mount
    public function mount()
{
    $this->loadProjects();
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
};
?>

<div>

    <!-- Add Project Button -->
    <button type="button" wire:click="openProjectModal" class="btn btn-primary" style="margin-bottom:1rem;">+ Add Project</button>

    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
    <thead >
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

    
@if ($successMessage)
    <div 
        x-data="{ show: true }"
        x-show="show"
        x-transition
        x-init="setTimeout(() => {
            show = false;
            @this.set('successMessage', null)
        }, 3000)" 
        class="mb-4 px-4 py-3 rounded-lg bg-green-100 border border-green-300 text-green-800 shadow transition-all duration-500">
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