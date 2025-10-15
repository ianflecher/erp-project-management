<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component
{
    public int $project_id;
    public string $project_name = '';
    public array $viewPhases = [];

    // Modal state + form fields
    public bool $showPhaseModal = false;
    public string $phase_name = '';
    public string $phase_start_date = '';
    public string $phase_end_date = '';
    public string $phase_description = '';
    public string $phaseError = '';
    public int $currentProjectId = 0;

    // Editing
    public bool $isEdit = false;
    public ?int $editPhaseId = null;

    public function mount()
    {
        $project = DB::table('projects')->where('project_id', $this->project_id)->first();
        if ($project) {
            $this->project_name = $project->project_name ?? 'Unnamed Project';
        }

        $this->loadPhases();
    }

    /**************************
     * ✅ AUTO UPDATE PHASE STATUS
     **************************/
    public function updatePhaseStatusFromTasks($phaseId)
    {
        $tasks = DB::table('tasks')
            ->where('phase_id', $phaseId)
            ->get();

        if ($tasks->isEmpty()) {
            // If no tasks, mark as Pending
            DB::table('project_phases')
                ->where('phase_id', $phaseId)
                ->update([
                    'status' => 'Pending',
                    'updated_at' => now(),
                ]);
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
            ->where('phase_id', $phaseId)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        $this->loadPhases();
    }

    /**************************
     * ✅ LOAD + CRUD METHODS
     **************************/
    public function loadPhases()
    {
        $this->viewPhases = DB::table('project_phases')
            ->where('project_id', $this->project_id)
            ->orderBy('start_date', 'asc')
            ->get()
            ->toArray();
    }

    public function openPhaseModal(int $projectId)
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->currentProjectId = $projectId;
        $this->showPhaseModal = true;
    }

    public function openEditPhaseModal(int $phase_id)
    {
        $phase = DB::table('project_phases')->where('phase_id', $phase_id)->first();

        if ($phase) {
            $this->editPhaseId = $phase->phase_id;
            $this->phase_name = $phase->phase_name;
            $this->phase_start_date = $phase->start_date;
            $this->phase_end_date = $phase->end_date;
            $this->phase_description = $phase->description ?? '';
            $this->currentProjectId = $phase->project_id;
            $this->isEdit = true;
            $this->showPhaseModal = true;
        }
    }

    public function closePhaseModal()
    {
        $this->showPhaseModal = false;
        $this->phaseError = '';
        $this->isEdit = false;
        $this->editPhaseId = null;
    }

    public function resetForm()
    {
        $this->phase_name = '';
        $this->phase_start_date = '';
        $this->phase_end_date = '';
        $this->phase_description = '';
        $this->phaseError = '';
    }

    public function savePhase()
    {
        if (!$this->phase_name || !$this->phase_start_date || !$this->phase_end_date) {
            $this->phaseError = 'All fields except description are required.';
            return;
        }

        if (Carbon::parse($this->phase_end_date)->lt(Carbon::parse($this->phase_start_date))) {
            $this->phaseError = 'End date cannot be earlier than start date.';
            return;
        }

        if ($this->isEdit && $this->editPhaseId) {
            DB::table('project_phases')->where('phase_id', $this->editPhaseId)->update([
                'phase_name' => $this->phase_name,
                'start_date' => $this->phase_start_date,
                'end_date' => $this->phase_end_date,
                'description' => $this->phase_description,
                'updated_at' => now(),
            ]);

            // ✅ Recheck status after editing
            $this->updatePhaseStatusFromTasks($this->editPhaseId);
        } else {
            $phaseId = DB::table('project_phases')->insertGetId([
                'project_id' => $this->currentProjectId,
                'phase_name' => $this->phase_name,
                'start_date' => $this->phase_start_date,
                'end_date' => $this->phase_end_date,
                'description' => $this->phase_description,
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ✅ Set initial status check
            $this->updatePhaseStatusFromTasks($phaseId);
        }

        $this->loadPhases();
        $this->closePhaseModal();
    }

    public function deletePhase($phase_id)
    {
        if (!$phase_id) return;

        DB::table('project_phases')->where('phase_id', $phase_id)->delete();
        $this->viewPhases = array_filter($this->viewPhases, fn($p) => $p->phase_id != $phase_id);
    }
}
?>


<div class="phase-container">
    <div class="phase-header">
        <h2>Phases — {{ $project_name }}</h2>
        <a href="{{ route('projects.home') }}" class="back-link">← Back to Projects</a>
    </div>

    <div style="margin-bottom: 1rem; text-align:right;">
        <button type="button" wire:click="openPhaseModal({{ $project_id }})" class="phase-btn phase-btn-green">
            + Add Phase
        </button>
    </div>

    <div class="phase-table-container">
        @if(count($viewPhases))
            <table class="phase-table">
                <thead>
                    <tr>
                        <th>Phase Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($viewPhases as $ph)
                        <tr>
                            <td>{{ $ph->phase_name }}</td>
                            <td>{{ $ph->start_date }}</td>
                            <td>{{ $ph->end_date }}</td>
                            <td>{{ $ph->status }}</td>
                            <td style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="{{ route('projects.task', ['phase_id' => $ph->phase_id]) }}" class="phase-btn phase-btn-green">
                                    View Tasks
                                </a>
                                <button type="button" wire:click="openEditPhaseModal({{ $ph->phase_id }})" class="phase-btn phase-btn-yellow">Edit</button>
                                <button type="button"
                                    onclick="if(confirm('Are you sure you want to delete this phase?')) { @this.call('deletePhase', {{ $ph->phase_id }}) }"
                                    class="phase-btn phase-btn-red">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="phase-no-data">No phases found for this project.</div>
        @endif
    </div>

    <!-- Modal (Add/Edit Phase) -->
    <div class="modal" style="display: {{ $showPhaseModal ? 'flex' : 'none' }};">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    {{ $isEdit ? 'Edit Phase #' . $editPhaseId : 'New Phase for Project #' . $currentProjectId }}
                </div>
                <button type="button" wire:click="closePhaseModal" class="btn btn-warning">×</button>
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
                    <button type="submit" class="btn btn-primary">
                        {{ $isEdit ? 'Update Phase' : 'Save Phase' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
