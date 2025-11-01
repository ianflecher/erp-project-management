<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.employeeapp')] class extends Component
{
    public array $pendingAsManager = [];
    public array $pendingAsMember = [];
    public array $acceptedProjects = [];
    public bool $hasAcceptedProject = false;

    public function mount(): void
    {
        $userEmployeeId = Auth::user()->employee_id;

        // Check if user has already accepted a project
        $this->hasAcceptedProject = DB::table('projects')
            ->where(function($q) use ($userEmployeeId) {
                $q->where('project_manager_id', $userEmployeeId)
                  ->where('manager_accepted', 1)
                  ->orWhereRaw("FIND_IN_SET(?, project_member_id)", [$userEmployeeId])
                  ->where('employee_accepted', 1);
            })
            ->exists();

        // Pending as Manager
        $this->pendingAsManager = DB::table('projects')
            ->where('project_manager_id', $userEmployeeId)
            ->where('manager_accepted', 0)
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();

        // Pending as Member
        $this->pendingAsMember = DB::table('projects')
            ->whereRaw("FIND_IN_SET(?, project_member_id)", [$userEmployeeId])
            ->where('employee_accepted', 0)
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();

        // Accepted Projects
        $this->acceptedProjects = DB::table('projects')
            ->where(function($q) use ($userEmployeeId) {
                $q->where('project_manager_id', $userEmployeeId)
                  ->where('manager_accepted', 1)
                  ->orWhereRaw("FIND_IN_SET(?, project_member_id)", [$userEmployeeId])
                  ->where('employee_accepted', 1);
            })
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();
    }

    // Accept project as Manager or Member
public function acceptProject(int $projectId, string $role): void
{
    if ($this->hasAcceptedProject) return; // Prevent multiple acceptance

    $field = $role === 'manager' ? 'manager_accepted' : 'employee_accepted';

    $updateData = [
        $field => 1,
        'accepted_at' => now()
    ];

    // Only update project status to 'Ongoing' if a member accepts
    if ($role === 'member') {
        $updateData['status'] = 'Ongoing';
    }

    DB::table('projects')->where('project_id', $projectId)->update($updateData);

    $this->mount(); // Refresh data
}


    // Decline project as Manager or Member
    public function declineProject(int $projectId, string $role): void
    {
        $field = $role === 'manager' ? 'manager_accepted' : 'employee_accepted';

        DB::table('projects')->where('project_id', $projectId)->update([
            $field => 0,
            'accepted_at' => null
        ]);

        $this->mount(); // Refresh data
    }
};
?>  

<div class="min-h-screen p-6 bg-[#0B260F]">
    <h1 class="text-3xl font-bold text-[#C8FFD4] mb-6">My Projects</h1>

    {{-- Pending as Manager --}}
    @if(count($pendingAsManager))
        <h2 class="text-xl text-yellow-300 font-semibold mb-2">Pending as Manager</h2>
        <ul class="space-y-2 mb-6">
            @foreach($pendingAsManager as $project)
                <li class="bg-[#124116] p-4 rounded-xl border border-[#1A5A20] flex justify-between items-center">
                    <div>
                        <h3 class="text-lg text-[#C8FFD4] font-bold">{{ $project->project_name }}</h3>
                        <p class="text-gray-300 mb-1">{{ $project->description }}</p>
                        <p class="text-sm text-gray-400">Start: {{ $project->start_date }} | End: {{ $project->end_date }}</p>
                        <p class="text-sm text-yellow-300 font-semibold">Status: Pending Manager Approval</p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="acceptProject({{ $project->project_id }}, 'manager')" 
                                class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-xl"
                                @if($hasAcceptedProject) disabled class="bg-gray-500 cursor-not-allowed" @endif>
                            Accept
                        </button>
                        <button wire:click="declineProject({{ $project->project_id }}, 'manager')" 
                                class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl">
                            Decline
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

   {{-- Pending as Member --}}
@if(count($pendingAsMember))
    <h2 class="text-xl text-yellow-300 font-semibold mb-2">Pending as Member</h2>
    <ul class="space-y-2 mb-6">
        @foreach($pendingAsMember as $project)
            <li class="bg-[#124116] p-4 rounded-xl border border-[#1A5A20] flex justify-between items-center">
                <div>
                    <h3 class="text-lg text-[#C8FFD4] font-bold">{{ $project->project_name }}</h3>
                    <p class="text-gray-300 mb-1">{{ $project->description }}</p>
                    <p class="text-sm text-gray-400">Start: {{ $project->start_date }} | End: {{ $project->end_date }}</p>
                    <p class="text-sm text-yellow-300 font-semibold">
                        Status: 
                        @if($project->employee_accepted == 0)
                            Pending Your Acceptance
                        @elseif($project->employee_accepted == 1)
                            Ongoing
                        @endif
                    </p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="acceptProject({{ $project->project_id }}, 'member')" 
                            class="bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-xl"
                            @if($hasAcceptedProject) disabled class="bg-gray-500 cursor-not-allowed" @endif>
                        Accept
                    </button>
                    <button wire:click="declineProject({{ $project->project_id }}, 'member')" 
                            class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-xl">
                        Decline
                    </button>
                </div>
            </li>
        @endforeach
    </ul>
@endif

    {{-- Accepted Projects --}}
    @if(count($acceptedProjects))
        <h2 class="text-xl text-green-300 font-semibold mb-2">Accepted Projects</h2>
        <ul class="space-y-2">
            @foreach($acceptedProjects as $project)
                <li class="bg-[#124116] p-4 rounded-xl border border-[#1A5A20]">
                    <h3 class="text-lg text-[#C8FFD4] font-bold">{{ $project->project_name }}</h3>
                    <p class="text-gray-300 mb-1">{{ $project->description }}</p>
                    <p class="text-sm text-gray-400">Start: {{ $project->start_date }} | End: {{ $project->end_date }}</p>
                    <p class="mt-2 text-green-300 font-semibold">
                        @if($project->project_manager_id == auth()->user()->employee_id)
                            You are the Manager
                        @else
                            You are a Member
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- No Projects --}}
    @if(count($pendingAsManager) + count($pendingAsMember) + count($acceptedProjects) === 0)
        <p class="text-gray-400 mt-4">No projects assigned yet.</p>
    @endif
</div>
