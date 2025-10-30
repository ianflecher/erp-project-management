<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $resources = [];
    public array $inventories = [];

    public bool $showResourceModal = false;
    public ?int $editing_id = null;

    public string $resource_name = '';
    public string $type = '';
    public float $unit_cost = 0.00;
    public float $availability_quantity = 0.00;
    public string $status = '';
    public ?int $inventory_id = null;

    public function mount()
    {
        $this->loadResources();
        $this->inventories = DB::table('inventories')->orderBy('name')->get()->toArray();
    }

    public function loadResources()
    {
        $this->resources = DB::table('resources')
            ->leftJoin('inventories', 'resources.inventory_id', '=', 'inventories.id')
            ->select('resources.*', 'inventories.name as inventory_name', 'inventories.quantity as inventory_qty')
            ->orderByDesc('resource_id')
            ->get()
            ->toArray();
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
            $this->inventory_id = $res->inventory_id;
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
        $data = [
            'resource_name' => $this->resource_name,
            'type' => $this->type,
            'unit_cost' => $this->unit_cost,
            'availability_quantity' => $this->availability_quantity,
            'status' => $this->status,
            'inventory_id' => $this->inventory_id,
            'updated_at' => now(),
        ];

        if ($this->editing_id) {
            DB::table('resources')->where('resource_id', $this->editing_id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('resources')->insert($data);
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
        $this->inventory_id = null;
    }
}
?>
<div class="resources-container">

    <div class="task-header" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="javascript:history.back()" class="back-link">← Back</a>
        <h2>Resources Management</h2>
        <button class="resources-btn resources-btn-green" wire:click="openAddModal">+ Add Resource</button>
    </div>

    <div class="resources-table-wrapper">
        @if(count($resources))
            <table class="resources-table">
                <thead>
                    <tr>
                        <th>Resource Name</th>
                        <th>Inventory Item</th>
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
                            <td>{{ $r->inventory_name ?? '—' }} (Qty: {{ $r->inventory_qty ?? 0 }})</td>
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

    <!-- === Modal === -->
<div class="resources-modal" style="display: {{ $showResourceModal ? 'flex' : 'none' }};">
    <div class="resources-modal-box">
        <div class="resources-modal-header">
            <h3>{{ $editing_id ? 'Edit Resource' : 'Add Resource' }}</h3>
            <button wire:click="closeResourceModal" class="resources-close-btn">✖</button>
        </div>

        <form wire:submit.prevent="saveResource" class="resources-form">
            <div class="resources-form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">

                <!-- Left Column -->
                <div class="resources-form-column">
                    <label>
                        <span>Resource Name</span>
                        <input type="text" wire:model="resource_name" required>
                    </label>

                    <label>
                        <span>Inventory Item</span>
                        <select wire:model="inventory_id" wire:change="$set('availability_quantity', $event.target.options[$event.target.selectedIndex].dataset.qty)" required>
                            <option value="">-- Select Inventory --</option>
                            @foreach($inventories as $inv)
                                <option value="{{ $inv->id }}" data-qty="{{ $inv->quantity }}">
                                    {{ $inv->name }} (Qty: {{ $inv->quantity }})
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Type</span>
                        <select wire:model="type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Labor">Labor</option>
                            <option value="Materials">Materials</option>
                            <option value="Overhead">Overhead</option>
                            <option value="Tool">Tool</option>
                            <option value="Facility">Facility</option>
                        </select>
                    </label>
                </div>

                <!-- Right Column -->
                <div class="resources-form-column">
                    <label>
                        <span>Unit Cost</span>
                        <input type="number" step="0.01" wire:model="unit_cost" required>
                    </label>

                    <label>
                        <span>Available Quantity</span>
                        <input type="number" wire:model="availability_quantity" readonly>
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

            </div>

            <div class="resources-modal-actions" style="margin-top:1rem;">
                <button type="submit" class="resources-btn resources-btn-green">
                    {{ $editing_id ? 'Update' : 'Save' }}
                </button>
                <button type="button" wire:click="closeResourceModal" class="resources-btn resources-btn-gray">Cancel</button>
            </div>
        </form>
    </div>
</div>

</div>
