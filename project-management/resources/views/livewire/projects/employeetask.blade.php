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

    public function startTask($taskId)
    {
        try {
            $task = DB::table('tasks')->where('task_id', $taskId)->first();

            if (!$task) {
                $this->dispatch('alert', type: 'error', message: 'Task not found!');
                return;
            }

            if ($task->assigned_to != $this->employeeId) {
                $this->dispatch('alert', type: 'error', message: 'You are not assigned to this task!');
                return;
            }

            $allowedStartStatuses = ['Pending', 'Not Started', 'Assigned', 'New', 'Rejected'];

            if (!in_array($task->status, $allowedStartStatuses)) {
                $this->dispatch('alert', type: 'error', message: "Cannot start task. Current status: {$task->status}");
                return;
            }

            DB::table('tasks')->where('task_id', $taskId)->update([
                'status' => 'In Progress',
                'progress_percentage' => 50,
                'updated_at' => now(),
            ]);

            if ($task->phase_id) {
                DB::table('project_phases')->where('phase_id', $task->phase_id)->update([
                    'status' => 'In Progress',
                    'updated_at' => now(),
                ]);
            }

            $this->loadTasks();
            $this->dispatch('alert', type: 'success', message: 'Task started successfully!');

        } catch (\Exception $e) {
            $this->dispatch('alert', type: 'error', message: 'An error occurred while starting the task.');
        }
    }

    public function submitTask($taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task || !$task->phase_id) return;

        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'Submitted to Manager',
            'progress_percentage' => 100,
            'updated_at' => now(),
        ]);

        $this->loadTasks();
        $this->updatePhaseStatus($task->phase_id);
    }

    public function approveTask($taskId)
    {
        $task = DB::table('tasks')->where('task_id', $taskId)->first();
        if (!$task || !$task->phase_id) return;

        DB::table('tasks')->where('task_id', $taskId)->update([
            'status' => 'Completed',
            'updated_at' => now(),
        ]);

        $this->loadTasks();
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

    private function updatePhaseStatus($phaseId)
    {
        $incompleteTasks = DB::table('tasks')
            ->where('phase_id', $phaseId)
            ->whereNotIn('status', ['Completed', 'Done'])
            ->count();

        if ($incompleteTasks === 0) {
            DB::table('project_phases')
                ->where('phase_id', $phaseId)
                ->update([
                    'status' => 'Completed',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('project_phases')
                ->where('phase_id', $phaseId)
                ->update([
                    'status' => 'In Progress',
                    'updated_at' => now(),
                ]);
        }
    }
};
?>

<style>
.task-card {
    transition: all 0.3s ease;
}

.task-card:hover {
    transform: translateY(-2px);
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.loading-button {
    position: relative;
}

.loading-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>
<div>
<div class="min-h-screen bg-gray-50 p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div class="flex items-center space-x-4">
            <a href="javascript:history.back()" class="flex items-center space-x-2 text-green-700 hover:text-green-800 transition-colors duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span class="font-semibold">Back</span>
            </a>
            <div class="h-6 w-px bg-gray-300"></div>
            <h1 class="text-3xl font-bold text-gray-800">My Tasks</h1>
        </div>
        
        <!-- Task Stats -->
        <div class="flex space-x-4">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-2 text-center">
                <div class="text-2xl font-bold text-green-600">{{ count($tasks) }}</div>
                <div class="text-sm text-gray-600">Total Tasks</div>
            </div>
        </div>
    </div>

    <!-- Task Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($tasks as $t)
            <div wire:key="task-{{ $t->task_id }}-{{ $t->status }}" class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-300 overflow-hidden">
                <!-- Task Header with Status Badge -->
                <div class="bg-gradient-to-r from-green-600 to-green-700 p-4 text-white">
                    <div class="flex justify-between items-start">
                        <h3 class="font-bold text-lg truncate">{{ $t->task_name }}</h3>
                        <span class="bg-white bg-opacity-20 text-xs font-semibold px-2 py-1 rounded-full">
                            {{ $t->role_type }}
                        </span>
                    </div>
                    <p class="text-green-100 text-sm mt-1 truncate">{{ $t->description }}</p>
                </div>

                <!-- Task Details -->
                <div class="p-4 space-y-3">
                    <!-- Timeline -->
                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2-2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ \Carbon\Carbon::parse($t->start_date)->format('M d, Y') }}</span>
                        <span class="text-gray-400">→</span>
                        <span>{{ \Carbon\Carbon::parse($t->end_date)->format('M d, Y') }}</span>
                    </div>

                    <!-- Status & Progress -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-gray-700">Status</span>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                @if($t->status === 'Completed' || $t->status === 'Done') bg-green-100 text-green-800
                                @elseif($t->status === 'In Progress') bg-blue-100 text-blue-800
                                @elseif($t->status === 'Submitted to Manager') bg-yellow-100 text-yellow-800
                                @elseif($t->status === 'Pending' || $t->status === 'Not Started') bg-gray-100 text-gray-800
                                @else bg-orange-100 text-orange-800 @endif">
                                {{ $t->status }}
                            </span>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Progress</span>
                                <span>{{ $t->progress_percentage }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full transition-all duration-500 ease-out" 
                                     style="width: {{ $t->progress_percentage }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-3 border-t border-gray-100">
                        @if ($t->role_type === 'Manager')
                            <div class="flex space-x-2">
                                <button onclick="approveTask({{ $t->task_id }})" 
                                        class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Approve</span>
                                </button>
                                <button onclick="rejectTask({{ $t->task_id }})" 
                                        class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    <span>Reject</span>
                                </button>
                            </div>
                        @else
                            @switch($t->status)
                                @case('Pending')
                                @case('Not Started')
                                    <button onclick="startTask({{ $t->task_id }}, this)" 
                                            class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-2 loading-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Start Task</span>
                                    </button>
                                    @break

                                @case('In Progress')
                                    <button onclick="submitTask({{ $t->task_id }}, this)" 
                                            class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg text-sm font-semibold transition-colors duration-200 flex items-center justify-center space-x-2 loading-button">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Submit for Review</span>
                                    </button>
                                    @break

                                @case('Submitted to Manager')
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
                                        <div class="flex items-center justify-center space-x-2 text-yellow-800">
                                            <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="font-semibold">Waiting for Manager Approval</span>
                                        </div>
                                    </div>
                                    @break

                                @case('Completed')
                                @case('Done')
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                                        <div class="flex items-center justify-center space-x-2 text-green-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="font-semibold">Task Completed</span>
                                        </div>
                                    </div>
                                    @break
                            @endswitch
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="col-span-full">
                <div class="text-center py-12">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks found</h3>
                    <p class="text-gray-500 max-w-md mx-auto">You don't have any tasks assigned to you at the moment. Tasks will appear here when they are assigned.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Loading State -->
    <div wire:loading class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 mx-auto mb-4"></div>
            <p class="text-gray-600">Loading tasks...</p>
        </div>
    </div>

    <!-- JavaScript to handle button clicks -->
    <script>
        function startTask(taskId, button) {
            // Show loading state
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div><span>Starting...</span>';
                button.disabled = true;
            }
            
            // Call Livewire method
            @this.call('startTask', taskId)
                .then(result => {
                    // Button will reset when component re-renders
                })
                .catch(error => {
                    // Reset button on error
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                });
        }

        function submitTask(taskId, button) {
            // Show loading state
            if (button) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div><span>Submitting...</span>';
                button.disabled = true;
            }
            
            // Call Livewire method
            @this.call('submitTask', taskId)
                .then(result => {
                    // Button will reset when component re-renders
                })
                .catch(error => {
                    // Reset button on error
                    if (button) {
                        button.innerHTML = originalHTML;
                        button.disabled = false;
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
        setInterval(() => {
            location.reload();
        }, 5000); // 5000 milliseconds = 5 seconds
    });


        function approveTask(taskId) {
            @this.call('approveTask', taskId);
        }

        function rejectTask(taskId) {
            @this.call('rejectTask', taskId);
        }
    </script>
</div>
    </div>