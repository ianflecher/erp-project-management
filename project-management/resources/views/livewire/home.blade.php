<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    // Projects list
    public array $projects = [];

    // Modal fields
    public string $project_name = '';
    public string $description = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $status = 'Planned';
    public float $budget_total = 0.0;
    public int $project_manager_id = 1;

    // Modal visibility
    public bool $showModal = false;

    public function mount()
    {
        $this->loadProjects();
    }

    public function loadProjects()
    {
        $this->projects = DB::table('projects')
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();
    }

    public function openModal()
    {
        $this->resetProjectFields();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function saveProject()
    {
        // Validate fields (optional)
        if (!$this->project_name || !$this->start_date || !$this->end_date) {
            return;
        }

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
        $this->closeModal();
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
};
?>

<div> <!-- SINGLE ROOT -->

    <!-- Page Header -->
    <div class="page-header">
        <h1>TGIF Project Management</h1>
    </div>

    <!-- Add Project Button -->
    <div style="margin:1rem 0;">
        <button type="button" wire:click="openModal" class="btn btn-primary">+ Add Project</button>
    </div>

    <!-- Projects Table -->
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>Manager ID</th>
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
                        <td>{{ $p->project_manager_id }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div class="modal" aria-hidden="{{ $showModal ? 'false' : 'true' }}" style="{{ $showModal ? 'display:flex;' : 'display:none;' }}">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>New Project</div>
                <button type="button" wire:click="closeModal" class="btn btn-warning">Close</button>
            </div>
            <div class="modal-body">
                <form class="form-grid">
                    <label>Project Name:<input type="text" wire:model="project_name" required/></label>
                    <label>Start Date:<input type="date" wire:model="start_date" required/></label>
                    <label>End Date:<input type="date" wire:model="end_date" required/></label>
                    <label>Description:<input type="text" wire:model="description"/></label>
                    <label>Status:
                        <select wire:model="status" required>
                            <option value="Planned">Planned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Done">Completed</option>
                        </select>
                    </label>
                    <label>Budget:<input type="number" wire:model="budget_total" min="0" step="0.01"/></label>
                    <label>Project Manager ID:<input type="number" wire:model="project_manager_id" min="1"/></label>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="saveProject" class="btn btn-primary">Save Project</button>
            </div>
        </div>
    </div>

</div>
