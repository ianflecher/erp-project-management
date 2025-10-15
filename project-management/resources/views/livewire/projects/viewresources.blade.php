<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $resources = [];
    public bool $showResourceModal = false;
    public ?int $editing_id = null;

    public string $resource_name = '';
    public string $type = '';
    public float $unit_cost = 0.00;
    public float $availability_quantity = 0.00;
    public string $status = '';

    public function mount()
    {
        $this->loadResources();
    }

    public function loadResources()
    {
        $this->resources = DB::table('resources')->orderByDesc('resource_id')->get()->toArray();
    }

    public function openAddModal()
    {
        $this->resetForm();
        $this->showResourceModal = true;
    }

    public function openEditModal($id)
    {
        $res = DB::table('resources')->where('resource_id', $id)->first();
        if ($res) {
            $this->editing_id = $res->resource_id;
            $this->resource_name = $res->resource_name;
            $this->type = $res->type;
            $this->unit_cost = $res->unit_cost;
            $this->availability_quantity = $res->availability_quantity;
            $this->status = $res->status;
            $this->showResourceModal = true;
        }
    }

    public function closeResourceModal()
    {
        $this->showResourceModal = false;
        $this->resetForm();
    }

    public function saveResource()
    {
        if ($this->editing_id) {
            DB::table('resources')->where('resource_id', $this->editing_id)->update([
                'resource_name' => $this->resource_name,
                'type' => $this->type,
                'unit_cost' => $this->unit_cost,
                'availability_quantity' => $this->availability_quantity,
                'status' => $this->status,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('resources')->insert([
                'resource_name' => $this->resource_name,
                'type' => $this->type,
                'unit_cost' => $this->unit_cost,
                'availability_quantity' => $this->availability_quantity,
                'status' => $this->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->closeResourceModal();
        $this->loadResources();
    }

    public function deleteResource($id)
    {
        DB::table('resources')->where('resource_id', $id)->delete();
        $this->loadResources();
    }

    public function resetForm()
    {
        $this->editing_id = null;
        $this->resource_name = '';
        $this->type = '';
        $this->unit_cost = 0.00;
        $this->availability_quantity = 0.00;
        $this->status = '';
    }
}
?>

<div class="resources-container">
    <div class="task-header">
        <a href="javascript:history.back()" class="back-link">← Back</a>
    </div>
    <div class="resources-header">
        <h2>Resources Management</h2>
        <button class="resources-btn resources-btn-green" wire:click="openAddModal">+ Add Resource</button>
    </div>


    <div class="resources-table-wrapper">
        @if(count($resources))
            <table class="resources-table">
                <thead>
                    <tr>
                        <th>Resource Name</th>
                        <th>Type</th>
                        <th>Unit Cost</th>
                        <th>Available Qty</th>
                        <th>Status</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resources as $r)
                        <tr>
                            <td>{{ $r->resource_name }}</td>
                            <td>{{ $r->type }}</td>
                            <td>₱{{ number_format($r->unit_cost, 2) }}</td>
                            <td>{{ $r->availability_quantity }}</td>
                            <td>
                                <span class="resources-status {{ strtolower($r->status) }}">
                                    {{ $r->status }}
                                </span>
                            </td>
                            <td class="resources-actions">
                                <button wire:click="openEditModal({{ $r->resource_id }})" class="resources-btn resources-btn-yellow">Edit</button>
                                <button wire:click="deleteResource({{ $r->resource_id }})" class="resources-btn resources-btn-red">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="resources-empty">No resources found.</div>
        @endif
    </div>

    
<!-- === Resource Modal === -->
<div class="resources-modal" style="display: {{ $showResourceModal ? 'flex' : 'none' }};">
    <div class="resources-modal-box">
        <div class="resources-modal-header">
            <h3>{{ $editing_id ? 'Edit Resource' : 'Add Resource' }}</h3>
            <button wire:click="closeResourceModal" class="resources-close-btn">✖</button>
        </div>

        <form wire:submit.prevent="saveResource" class="resources-form">
            <div class="resources-form-grid">
                <label>
                    <span>Resource Name</span>
                    <input type="text" wire:model="resource_name" required>
                </label>
                <label>
    <span>Type</span>
    <select wire:model="type" required>
        <option value="">-- Select Type --</option>
        <option value="Consumable">Consumable</option>
        <option value="Equipment">Equipment</option>
        <option value="Service">Service</option>
        <option value="Tool">Tool</option>
        <option value="Facility">Facility</option>
    </select>
</label>

                <label>
                    <span>Unit Cost</span>
                    <input type="number" step="0.01" wire:model="unit_cost" required>
                </label>
                <label>
                    <span>Available Quantity</span>
                    <input type="number" step="0.01" wire:model="availability_quantity" required>
                </label>
                <label>
    <span>Status</span>
    <select wire:model="status" required>
        <option value="">-- Select Status --</option>
        <option value="Active">Active</option>
        <option value="Unavailable">Unavailable</option>
        <option value="Maintenance">Maintenance</option>
        <option value="Reserved">Reserved</option>
    </select>
</label>

            </div>

            <div class="resources-modal-actions">
                <button type="submit" class="resources-btn resources-btn-green">
                    {{ $editing_id ? 'Update' : 'Save' }}
                </button>
                <button type="button" wire:click="closeResourceModal" class="resources-btn resources-btn-gray">Cancel</button>
            </div>
        </form>
    </div>
</div>


</div>
