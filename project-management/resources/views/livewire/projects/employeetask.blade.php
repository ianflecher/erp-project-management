<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.employeeapp')] class extends Component
{
    public $tasks = [];
    public $employeeId;
    public $user;

    public function mount()
    {
        $this->user = Auth::user();

        $this->employeeId = $this->user->employee_id;

        if (!$this->employeeId) {
            $this->tasks = [];
            return;
        }

        $this->loadTasks();
    }

    private function loadTasks()
{
    $allTasks = collect();

    // 1. Manager tasks: tasks submitted to this employee for projects they manage
    $managerProjectIds = DB::table('projects')
        ->where('project_manager_id', $this->employeeId)
        ->pluck('project_id');

    if ($managerProjectIds->isNotEmpty()) {
        $managerTasks = DB::table('tasks')
            ->leftJoin('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->whereIn('project_phases.project_id', $managerProjectIds)
            ->where('tasks.status', 'Submitted to Manager')
            ->select('tasks.*')
            ->get()
            ->map(function ($task) {
                $task->role_type = 'Manager';
                return $task;
            });

        $allTasks = $allTasks->merge($managerTasks);
    }

    // 2. Member tasks: tasks assigned to this employee in projects they are part of
    $memberProjectIds = DB::table('projects')
        ->where('project_manager_id', $this->employeeId)
        ->orWhereRaw("FIND_IN_SET(?, project_member_id)", [$this->employeeId])
        ->pluck('project_id');

    if ($memberProjectIds->isNotEmpty()) {
        $memberTasks = DB::table('tasks')
            ->leftJoin('project_phases', 'tasks.phase_id', '=', 'project_phases.phase_id')
            ->whereIn('project_phases.project_id', $memberProjectIds)
            ->where('tasks.assigned_to', $this->employeeId)
            ->select('tasks.*')
            ->get()
            ->map(function ($task) {
                $task->role_type = 'Employee';
                return $task;
            });

        $allTasks = $allTasks->merge($memberTasks);
    }

    $this->tasks = $allTasks->sortBy('status')->values();
}

private function updatePhaseStatus($phaseId)
{
    // Check if all tasks in the phase are completed
    $incompleteTasks = DB::table('tasks')
        ->where('phase_id', $phaseId)
        ->whereNotIn('status', ['Completed', 'Done'])
        ->count();

    if ($incompleteTasks === 0) {
        // All tasks completed, mark phase as completed
        DB::table('project_phases')
            ->where('phase_id', $phaseId)
            ->update([
                'status' => 'Completed',
                'updated_at' => now(),
            ]);
    } else {
        // Optional: if some tasks are not complete, set phase as "In Progress"
        DB::table('project_phases')
            ->where('phase_id', $phaseId)
            ->update([
                'status' => 'In Progress',
                'updated_at' => now(),
            ]);
    }
}



    public function startTask($taskId)
    {
        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'In Progress',
            'progress_percentage' => 50,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
    }

    public function submitTask($taskId)
{
    // Fetch the task first
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task || !$task->phase_id) return; // safety check

    // Update task status
    DB::table('tasks')->where('task_id', $taskId)->update([
        'status' => 'Submitted to Manager',
        'progress_percentage' => 100,
        'updated_at' => now(),
    ]);

    $this->loadTasks();

    // Update phase status
    $this->updatePhaseStatus($task->phase_id);
}

public function approveTask($taskId)
{
    // Fetch the task first
    $task = DB::table('tasks')->where('task_id', $taskId)->first();
    if (!$task || !$task->phase_id) return; // safety check

    // Approve the task
    DB::table('tasks')->where('task_id', $taskId)->update([
        'status' => 'Completed',
        'updated_at' => now(),
    ]);

    $this->loadTasks();

    // Update phase status
    $this->updatePhaseStatus($task->phase_id);
}


    public function rejectTask($taskId)
    {
        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'In Progress',
            'progress_percentage' => 50,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
    }
};
?>


<div class="text-white">
    <h1 class="text-2xl font-bold mb-4">
    Tasks
</h1>

<table class="w-full bg-[#124116] text-white rounded-lg overflow-hidden">
    <thead class="bg-green-800">
        <tr>
            <th class="p-2 text-left">Task</th>
            <th class="p-2 text-left">Description</th>
            <th class="p-2 text-left">Start</th>
            <th class="p-2 text-left">End</th>
            <th class="p-2 text-left">Status</th>
            <th class="p-2 text-left">Progress</th>
            <th class="p-2 text-left">Action</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($tasks as $t)
        <tr class="border-b border-green-900">
            <td class="p-2">{{ $t->task_name }}</td>
            <td class="p-2">{{ $t->description }}</td>
            <td class="p-2">{{ $t->start_date }}</td>
            <td class="p-2">{{ $t->end_date }}</td>
            <td class="p-2">{{ $t->status }}</td>
            <td class="p-2">{{ $t->progress_percentage }}%</td>
            <td class="p-2">
                @if ($t->role_type === 'Manager')
                    <button wire:click="approveTask({{ $t->task_id }})" class="task-btn task-btn-green">Approve</button>
                    <button wire:click="rejectTask({{ $t->task_id }})" class="task-btn task-btn-red">Reject</button>
                @else
                    @switch($t->status)
                        @case('Pending')
                        @case('Not Started')
                            <button wire:click="startTask({{ $t->task_id }})" class="task-btn task-btn-yellow">Start</button>
                            @break

                        @case('In Progress')
                            <button wire:click="submitTask({{ $t->task_id }})" class="task-btn task-btn-green">Submit for Checking</button>
                            @break

                        @case('Submitted to Manager')
                            <span>⏳ Waiting for Manager Approval</span>
                            @break

                        @case('Completed')
                        @case('Done')
                            <span>✅ Completed</span>
                            @break
                    @endswitch
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="p-3 text-center text-gray-300">No tasks found.</td>
        </tr>
    @endforelse
    </tbody>
</table>

</div>
