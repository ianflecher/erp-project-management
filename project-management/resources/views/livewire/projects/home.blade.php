<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $projects = [];
    public array $managers = [];

    public bool $showProjectModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public ?int $projectToDelete = null;
    public array $editProject = [];

    // Project fields
    public string $project_name = '';
    public string $description = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $status = 'Paused'; // Changed default to Paused
    public float $budget_total = 0.0;
    public ?int $project_manager_id = null;

    public function mount()
    {
        $this->loadProjects();
        $this->loadManagers();
    }

    /***************
     * Load Data
     ***************/
    public function loadProjects()
    {
        $this->projects = DB::table('projects')
            ->leftJoin('hr_employees', 'projects.project_manager_id', '=', 'hr_employees.employee_id')
            ->select('projects.*', 'hr_employees.full_name as manager_name')
            ->orderBy('projects.start_date', 'desc')
            ->get()
            ->toArray();
    }

    public function loadManagers()
    {
        $this->managers = DB::table('hr_employees')
            ->where('role', '!=', 'Admin') // exclude Admin
            ->orderBy('full_name')
            ->get()
            ->toArray();
    }

    /***************
     * Create Project
     ***************/
    public function openProjectModal() { $this->resetProjectFields(); $this->showProjectModal = true; }
    public function closeProjectModal() { $this->showProjectModal = false; }

    public function saveProject()
{
    if (!$this->project_name || !$this->start_date || !$this->end_date) return;

    // Insert project first
    $projectId = DB::table('projects')->insertGetId([
        'project_name'       => $this->project_name,
        'description'        => $this->description,
        'start_date'         => $this->start_date,
        'end_date'           => $this->end_date,
        'status'             => 'Paused',
        'budget_total'       => $this->budget_total,
        'project_manager_id' => $this->project_manager_id,
        'project_member_id'  => null,
        'employee_accepted'  => 0,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    // Create a main budget record for the project (phase_id = 0 for project-level budget)
    $budgetId = DB::table('budgets')->insertGetId([
        'project_id'     => $projectId,
        'phase_id'       => 0, // Using 0 for project-level budget
        'task_id'        => null, // No specific task
        'estimated_cost' => $this->budget_total,
        'actual_cost'    => 0.00,
        'variance'       => 0.00,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    // Insert into budget_approvals table with the correct budget_id
    DB::table('budget_approvals')->insert([
        'budget_id'    => $budgetId, // Use the actual budget ID we just created
        'requested_by' => auth()->id(),
        'status'       => 'pending',
        'remarks'      => null,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $this->closeProjectModal();
    $this->loadProjects();
}

    public function resetProjectFields()
    {
        $this->project_name = '';
        $this->description = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->status = 'Paused'; // Changed to Paused
        $this->budget_total = 0.0;
        $this->project_manager_id = null;
    }

    /***************
     * Edit Project
     ***************/
    public function openEditModal($projectId)
    {
        $project = DB::table('projects')->where('project_id', $projectId)->first();
        if ($project) {
            $this->editProject = (array) $project;
            $this->showEditModal = true;
        }
    }

    public function resumeProject($projectId)
    {
        DB::table('projects')
            ->where('project_id', $projectId)
            ->update([
                'status' => 'Pending',  // Resume to active status
                'updated_at' => now()
            ]);

        $this->loadProjects();
    }

    public function closeEditModal() { $this->showEditModal = false; $this->editProject = []; }

    public function updateProject()
    {
        if (!isset($this->editProject['project_id'])) return;

        DB::table('projects')
            ->where('project_id', $this->editProject['project_id'])
            ->update([
                'project_name'       => $this->editProject['project_name'] ?? '',
                'description'        => $this->editProject['description'] ?? '',
                'budget_total'       => $this->editProject['budget_total'] ?? 0,
                'project_manager_id' => $this->editProject['project_manager_id'] ?? null,
                'start_date'         => $this->editProject['start_date'] ?? null,
                'end_date'           => $this->editProject['end_date'] ?? null,
                'updated_at'         => now(),
                // DO NOT update 'employee_accepted' here
            ]);

        $this->closeEditModal();
        $this->loadProjects();
    }

    /***************
     * Delete Project
     ***************/
    public function confirmDelete($projectId)
    {
        $this->projectToDelete = $projectId;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->projectToDelete = null;
    }

    public function deleteProject()
{
    if ($this->projectToDelete) {
        // First get all budget IDs for this project
        $budgetIds = DB::table('budgets')
            ->where('project_id', $this->projectToDelete)
            ->pluck('budget_id')
            ->toArray();

        // Delete from budget_approvals
        if (!empty($budgetIds)) {
            DB::table('budget_approvals')->whereIn('budget_id', $budgetIds)->delete();
        }

        // Delete from budgets
        DB::table('budgets')->where('project_id', $this->projectToDelete)->delete();

        // Delete from projects
        DB::table('projects')->where('project_id', $this->projectToDelete)->delete();

        $this->closeDeleteModal();
        $this->loadProjects();
    }
}
};
?>


<div class="phase-container">

<!-- Top Buttons Container -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">

    <!-- Add Project Button -->
    <button type="button" wire:click="openProjectModal" class="phase-btn phase-btn-green">
        + Add Project
    </button>

    <!-- Resource Allocation Button -->
    <a href="{{ route('projects.resources') }}" class="phase-btn phase-btn-green">
        Allocate Resources
    </a>

</div>




    <div class="phase-table-container">
    <table class="phase-table">
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
        @php
            $isPaused = $p->status === 'Paused';
        @endphp
        <tr style="border-bottom:1px solid #eee; {{ $isPaused ? 'background-color:#e0e0e0;color:#666;' : '' }}">
            <td style="padding:8px;">{{ $p->project_name }}</td>
            <td style="padding:8px;">{{ $p->description }}</td>
            <td style="padding:8px;">{{ $p->start_date }}</td>
            <td style="padding:8px;">{{ $p->end_date }}</td>
            <td style="padding:8px;">{{ $p->status }}</td>
            <td style="padding:8px;">₱{{ number_format($p->budget_total, 2) }}</td>
            <td style="padding:8px;">{{ $p->manager_name ?? 'Unknown' }}</td>

            @if($isPaused)
                <!-- Centered Resume Button -->
                <td colspan="4" style="text-align:center; padding:8px;">
                    <!-- <button wire:click="resumeProject({{ $p->project_id }})" class="phase-btn phase-btn-green">
                        Resume
                    </button> -->
                </td>
            @else
                <!-- Member -->
                <td>
                    <a href="{{ route('projects.members', ['project_id' => $p->project_id]) }}" class="phase-btn phase-btn-green">
                        View
                    </a>
                </td>

                <!-- Phases -->
                <td>
                    <a href="{{ route('projects.phase', ['project_id' => $p->project_id]) }}" class="phase-btn phase-btn-green">
                        View
                    </a>
                </td>

                <!-- Gantt -->
                <td>
                    <button onclick="loadGantt({{ $p->project_id }})" class="phase-btn phase-btn-green">
                        View
                    </button>
                </td>

                <!-- Actions -->
                <td style="padding:8px;text-align:center;">
                    <div style="display:flex;justify-content:center;align-items:center;gap:0.3rem;">
                        <button wire:click="openEditModal({{ $p->project_id }})"
                                class="phase-btn phase-btn-yellow">
                            Edit
                        </button>
                        <button wire:click="confirmDelete({{ $p->project_id }})"
                                class="phase-btn phase-btn-red">
                            Delete
                        </button>
                    </div>
                </td>
            @endif
        </tr>
    @endforeach
</tbody>

</table>



<div id="gantt_here" style="width:100%; height:500px;"></div>

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
<link rel="stylesheet" href="{{ asset('css/dhtmlxgantt.css') }}">
<script src="{{ asset('js/dhtmlxgantt.js') }}"></script>
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