<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('components.layouts.app')] class extends Component
{
    public int $project_id;
    public string $project_name = '';
    public array $projectMembers = [];
    public array $employees = [];
    public string $newMemberName = '';
    public ?int $selectedMemberId = null;

    public function mount($project_id)
    {
        $this->project_id = $project_id;
        $this->loadProjectData();
    }

    private function loadProjectData()
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();

        if ($project) {
            $this->project_name = $project->project_name ?? 'Unnamed Project';
        }

        $this->employees = DB::table('hr_employees')->orderBy('full_name')->get()->toArray();

        $raw = $project->project_member_id ?? '';
        $ids = collect(explode(',', $raw))
            ->filter(fn($x) => $x !== '' && $x !== null)
            ->map(fn($x) => (int)$x)
            ->values()
            ->toArray();

        $this->projectMembers = [];

        if (count($ids)) {
            $emps = DB::table('hr_employees')->whereIn('employee_id', $ids)->get();

            foreach ($ids as $id) {
                $emp = $emps->firstWhere('employee_id', $id);
                if ($emp) {
                    $this->projectMembers[] = [
                        'employee_id' => (int)$emp->employee_id,
                        'full_name' => $emp->full_name,
                        'role' => $emp->role,
                    ];
                }
            }
        }
    }

    public function addMember()
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();
        $members = $project->project_member_id ? explode(',', $project->project_member_id) : [];


        // Add new external member
        if ($this->newMemberName) {
            $name = trim($this->newMemberName);
            $email = strtolower(str_replace(' ', '.', $name)) . '@company.com';

            $employeeId = DB::table('hr_employees')->insertGetId([
                'full_name' => $name,
                'role' => 'Employee',
                'email' => $email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $members[] = $employeeId;
            $this->newMemberName = '';
        }

        // Add existing member
        if ($this->selectedMemberId) {
            if (!in_array($this->selectedMemberId, $members)) {
                $members[] = $this->selectedMemberId;
            }
            $this->selectedMemberId = null;
        }

        DB::table('projects')->where('project_id', $this->project_id)
            ->update([
                'project_member_id' => implode(',', $members),
                'updated_at' => now(),
            ]);

        $this->loadProjectData();
    }

    public function removeMember($employeeId)
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();
        $members = $project->project_member_id ? explode(',', $project->project_member_id) : [];

        $members = array_filter($members, fn($id) => $id != $employeeId);

        DB::table('projects')->where('project_id', $this->project_id)
            ->update([
                'project_member_id' => implode(',', $members),
                'updated_at' => now(),
            ]);

        $this->loadProjectData();
    }
};
?>
<div class="member-container">
    <div class="member-header">
        <h2>Project Members — {{ $project_name }}</h2>
        <a href="{{ route('projects.home') }}" style="color:#15803d; text-decoration:none; font-weight:600;">← Back to Projects</a>
    </div>

    <div class="member-list">
        @if(count($projectMembers))
            @foreach($projectMembers as $member)
                <div class="member-card" wire:key="member-{{ $member['employee_id'] }}">
                    <div class="member-details">
                        <div class="member-name">{{ $member['full_name'] }}</div>
                        <div class="member-role">{{ $member['role'] }}</div>
                    </div>
                    <div class="member-actions">
                        <button class="remove-btn" wire:click="removeMember({{ $member['employee_id'] }})">Remove</button>
                    </div>
                </div>
            @endforeach
        @else
            <div class="no-members">No members yet.</div>
        @endif
    </div>

    <div class="member-form">
        <h3>Add New Member</h3>
        <form wire:submit.prevent="addMember">
            <div class="form-group">
                <label for="newMemberName">New Member Name (External)</label>
                <input type="text" id="newMemberName" wire:model.defer="newMemberName" placeholder="Enter full name">
            </div>

            <div class="form-group">
                <label for="selectedMemberId">Select Existing Employee</label>
                <select id="selectedMemberId" wire:model="selectedMemberId">
                    <option value="">-- Select Employee --</option>
                    @foreach ($employees as $emp)
                        @if(!collect($projectMembers)->pluck('employee_id')->contains($emp->employee_id))
                            <option value="{{ $emp->employee_id }}">{{ $emp->full_name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div style="text-align:right; margin-top:1rem;">
                <button type="submit" class="add-btn">Add Member</button>
            </div>
        </form>
    </div>
</div>
