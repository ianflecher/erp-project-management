<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component
{
    public int $phase_id;
    public string $phase_name = '';
    public string $project_name = '';
    public array $tasks = [];

    // === Add/Edit Task Modal fields ===
    public bool $showTaskModal = false;
    public bool $isEditing = false;
    public int $editingTaskId = 0;
    public string $task_name = '';
    public string $task_start_date = '';
    public string $task_end_date = '';
    public string $task_description = '';
    public string $taskError = '';

    // === Delete Confirmation Modal ===
    public bool $showDeleteModal = false;
    public int $deleteTaskId = 0;
    public string $deleteTaskName = '';

    public function mount(int $phase_id)
    {
        $this->phase_id = $phase_id;

        $phase = DB::table('project_phases')->where('phase_id', $phase_id)->first();
        if ($phase) {
            $this->phase_name = $phase->phase_name;
            $project = DB::table('projects')->where('project_id', $phase->project_id)->first();
            $this->project_name = $project->project_name ?? 'Unnamed Project';
        }

        $this->loadTasks();
    }

    /**************************
     * ✅ LOAD TASKS
     **************************/
    public function loadTasks()
    {
        $this->tasks = DB::table('tasks')
            ->leftJoin('hr_employees', 'tasks.assigned_to', '=', 'hr_employees.employee_id')
            ->select(
                'tasks.*',
                'hr_employees.full_name as assigned_name'
            )
            ->where('tasks.phase_id', $this->phase_id)
            ->orderBy('tasks.start_date', 'asc')
            ->get()
            ->toArray();
    }

    /**************************
     * ✅ UPDATE PHASE STATUS
     **************************/
    public function updatePhaseStatus()
    {
        $tasks = DB::table('tasks')->where('phase_id', $this->phase_id)->get();

        if ($tasks->isEmpty()) {
            DB::table('project_phases')
                ->where('phase_id', $this->phase_id)
                ->update(['status' => 'Pending', 'updated_at' => now()]);
            return;
        }

        $total = $tasks->count();
        $completed = $tasks->where('status', 'Completed')->count();
        $inProgress = $tasks->where('status', 'In Progress')->count();

        $newStatus = 'Pending';
        if ($completed === $total) {
            $newStatus = 'Completed';
        } elseif ($inProgress > 0 || $completed > 0) {
            $newStatus = 'In Progress';
        }

        DB::table('project_phases')
            ->where('phase_id', $this->phase_id)
            ->update(['status' => $newStatus, 'updated_at' => now()]);
    }

    /**************************
     * ✅ TOGGLE STATUS BUTTON
     **************************/
    public function toggleTaskStatus($task_id)
    {
        $task = DB::table('tasks')->where('task_id', $task_id)->first();
        if (!$task) return;

        $newStatus = 'Pending';
        $newProgress = 0;

        if ($task->status === 'Pending') {
            $newStatus = 'In Progress';
            $newProgress = 50;
        } elseif ($task->status === 'In Progress') {
            $newStatus = 'Completed';
            $newProgress = 100;
        } elseif ($task->status === 'Completed') {
            $newStatus = 'Pending';
            $newProgress = 0;
        }

        DB::table('tasks')
            ->where('task_id', $task_id)
            ->update([
                'status' => $newStatus,
                'progress_percentage' => $newProgress,
                'updated_at' => now(),
            ]);

        $this->updatePhaseStatus();
        $this->loadTasks();
    }

    /**************************
     * ✅ TASK MODAL CONTROLS
     **************************/
    public function openTaskModal(int $phase_id)
    {
        $this->resetTaskForm();
        $this->isEditing = false;
        $this->phase_id = $phase_id;
        $this->showTaskModal = true;
    }

    public function openEditTaskModal(int $task_id)
    {
        $task = DB::table('tasks')->where('task_id', $task_id)->first();
        if (!$task) return;

        $this->isEditing = true;
        $this->editingTaskId = $task_id;
        $this->task_name = $task->task_name;
        $this->task_start_date = $task->start_date;
        $this->task_end_date = $task->end_date;
        $this->task_description = $task->description ?? '';

        $this->showTaskModal = true;
    }

    public function closeTaskModal()
    {
        $this->showTaskModal = false;
        $this->taskError = '';
    }

    public function resetTaskForm()
    {
        $this->task_name = '';
        $this->task_start_date = '';
        $this->task_end_date = '';
        $this->task_description = '';
    }

    /**************************
     * ✅ SAVE / UPDATE TASK
     **************************/
    public function saveTask()
    {
        if (!$this->task_name || !$this->task_start_date || !$this->task_end_date) {
            $this->taskError = 'Please fill in all required fields.';
            return;
        }

        if (Carbon::parse($this->task_end_date)->lt(Carbon::parse($this->task_start_date))) {
            $this->taskError = 'End date cannot be earlier than start date.';
            return;
        }

        if ($this->isEditing) {
            DB::table('tasks')->where('task_id', $this->editingTaskId)->update([
                'task_name' => $this->task_name,
                'description' => $this->task_description,
                'start_date' => $this->task_start_date,
                'end_date' => $this->task_end_date,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('tasks')->insert([
                'phase_id' => $this->phase_id,
                'task_name' => $this->task_name,
                'description' => $this->task_description,
                'start_date' => $this->task_start_date,
                'end_date' => $this->task_end_date,
                'status' => 'Pending',
                'progress_percentage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->updatePhaseStatus();
        $this->loadTasks();
        $this->closeTaskModal();
    }

    /**************************
     * ✅ DELETE TASK
     **************************/
    public function confirmDeleteTask(int $task_id)
    {
        $task = DB::table('tasks')->where('task_id', $task_id)->first();
        if (!$task) return;

        $this->deleteTaskId = $task_id;
        $this->deleteTaskName = $task->task_name;
        $this->showDeleteModal = true;
    }

    public function deleteTaskConfirmed()
    {
        DB::table('tasks')->where('task_id', $this->deleteTaskId)->delete();
        $this->updatePhaseStatus();
        $this->loadTasks();
        $this->showDeleteModal = false;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
    }
}
?>
<!-- === Task Page === -->
<div class="phase-container">
    <div class="phase-header">
        <h2>Tasks — {{ $phase_name }} ({{ $project_name }})</h2>
        <a href="javascript:history.back()" class="back-link">← Back</a>
    </div>

    <div style="margin-bottom:1rem; text-align:right;">
        <button type="button" wire:click="openTaskModal({{ $phase_id }})" class="task-btn task-btn-green">
            + Add Task
        </button>
    </div>

    <div class="phase-table">
        @if(count($tasks))
            <table class="phase-table">
                <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Assigned To</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                        <tr>
                            <td>{{ $task->task_name }}</td>
                            <td>{{ $task->assigned_name ?? 'Unassigned' }}</td>
                            <td>{{ $task->start_date }}</td>
                            <td>{{ $task->end_date }}</td>
                            <td>{{ $task->status }}</td>
                            <td>{{ $task->progress_percentage }}%</td>
                            <td style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                                <button type="button" wire:click="toggleTaskStatus({{ $task->task_id }})" class="task-btn task-btn-yellow">
                                    Next Status
                                </button>
                                <button type="button" wire:click="openEditTaskModal({{ $task->task_id }})" class="task-btn task-btn-yellow">Edit</button>
                                <button type="button" wire:click="confirmDeleteTask({{ $task->task_id }})" class="task-btn task-btn-red">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="task-no-data">No tasks found for this phase.</div>
        @endif
    </div>

    <!-- === Add/Edit Modal === -->
    <div class="task-modal" style="display: {{ $showTaskModal ? 'flex' : 'none' }};">
        <div class="task-modal-dialog">
            <div class="task-modal-header">
                <h3>{{ $isEditing ? 'Edit Task:' : 'New Task for Phase:' }} <span>{{ $phase_name }}</span></h3>
                <button type="button" wire:click="closeTaskModal" class="task-btn task-btn-yellow">×</button>
            </div>

            <div class="task-modal-body">
                @if($taskError)
                    <p class="task-modal-error">{{ $taskError }}</p>
                @endif

                <form wire:submit.prevent="saveTask" class="task-modal-form">
                    <div class="task-modal-field">
                        <label>Task Name</label>
                        <input type="text" wire:model="task_name" required />
                    </div>

                    <div class="task-modal-field">
                        <label>Start Date</label>
                        <input type="date" wire:model="task_start_date" required />
                    </div>

                    <div class="task-modal-field">
                        <label>End Date</label>
                        <input type="date" wire:model="task_end_date" required />
                    </div>

                    <div class="task-modal-field">
                        <label>Description</label>
                        <textarea wire:model="task_description" rows="3"></textarea>
                    </div>

                    <button type="submit" class="task-btn task-btn-green task-modal-submit">
                        {{ $isEditing ? 'Update Task' : 'Save Task' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- === Delete Confirmation Modal === -->
    <div class="task-modal" style="display: {{ $showDeleteModal ? 'flex' : 'none' }};">
        <div class="task-modal-dialog">
            <div class="task-modal-header"><h3>Confirm Delete</h3></div>
            <div class="task-modal-body" style="text-align:center;">
                <p>Are you sure you want to delete <strong>{{ $deleteTaskName }}</strong>?</p>
                <div style="display:flex;justify-content:center;gap:0.5rem;margin-top:1rem;">
                    <button wire:click="deleteTaskConfirmed" class="task-btn task-btn-red">Yes, Delete</button>
                    <button wire:click="cancelDelete" class="task-btn task-btn-yellow">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
