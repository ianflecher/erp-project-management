<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    // Main data
    public array $resources = [];
    public array $inventories = [];

    // UI state
    public bool $showResourceModal = false;
    public ?int $editing_id = null;

    // Form fields
    public string $resource_name = '';
    public string $type = '';
    public float $unit_cost = 0.00;
    public float $availability_quantity = 0.00;
    public string $status = '';
    public ?int $inventory_id = null;
    public array $groupedResources = [];


    // Helpers
    public bool $isLabor = false;
    public array $filteredInventories = [];

    public function mount()
    {
        $this->loadResources();
        $this->inventories = DB::table('inventories')->orderBy('name')->get()->toArray();
        $this->filteredInventories = $this->inventories;
    }

    public function updatedType($value)
    {
        $this->type = $value;
        $this->isLabor = (strtolower($value) === 'labor');

        $this->filteredInventories = array_values(array_filter($this->inventories, function ($inv) use ($value) {
            $cat = strtolower(trim((string)($inv->category ?? '')));
            $val = strtolower(trim((string)$value));

            if ($val === 'labor') return $cat === 'labor';
            if ($val === 'tool') return $cat === 'tool' || str_contains($cat, 'tool');
            if ($val === 'materials') return $cat !== 'labor' && !str_contains($cat, 'tool');

            return $cat === $val;
        }));

        if ($this->isLabor) {
            $this->inventory_id = null;
            $this->availability_quantity = 0;
        } else {
            if ($this->inventory_id) {
                $inv = collect($this->inventories)->firstWhere('id', $this->inventory_id);
                $this->availability_quantity = $inv->quantity ?? 0;
                // Do not overwrite resource_name
            }
        }
    }

    public function updatedInventory_id($value)
    {
        if (!$value) {
            $this->availability_quantity = $this->isLabor ? 0 : $this->availability_quantity;
            return;
        }

        $inv = collect($this->inventories)->firstWhere('id', $value);

        if ($inv) {
            // Only fill resource_name if empty
            if (!$this->resource_name) {
                $this->resource_name = $inv->name;
            }

            $this->availability_quantity = $this->isLabor ? 0 : ($inv->quantity ?? 0);
        }
    }

    public function loadResources()
{
    $this->resources = DB::table('resources')
        ->leftJoin('inventories', 'resources.inventory_id', '=', 'inventories.id')
        ->select('resources.*', 'inventories.name as inventory_name', 'inventories.quantity as inventory_qty')
        ->orderByDesc('resource_id')
        ->get()
        ->toArray();

    // Group by type
    $this->groupedResources = [];
    foreach ($this->resources as $r) {
        $typeKey = $r->type ?: 'Others';
        $this->groupedResources[$typeKey][] = $r;
    }
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
            $this->status = $res->status;
            $this->inventory_id = $res->inventory_id;
            $this->isLabor = (strtolower($res->type) === 'labor');

            $this->updatedType($this->type);

            if (!$this->isLabor && $this->inventory_id) {
                $inv = collect($this->inventories)->firstWhere('id', $this->inventory_id);
                $this->availability_quantity = $inv->quantity ?? 0;
            } else {
                $this->availability_quantity = 0;
            }

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
        if (!$this->resource_name || !$this->type || !$this->status) return;

        $data = [
            'resource_name' => $this->resource_name,
            'type' => $this->type,
            'unit_cost' => $this->unit_cost,
            'availability_quantity' => $this->isLabor ? 0 : $this->availability_quantity,
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
        $this->isLabor = false;
        $this->filteredInventories = $this->inventories;
    }
}
?>

<style>
/* Container */
.resources-container {
    font-family: 'Segoe UI', sans-serif;
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
    background: #f7f9f7;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Header */
.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.task-header h2 {
    color: #2f7a2f;
    font-weight: 700;
}

.back-link {
    color: #2f7a2f;
    font-weight: 500;
    text-decoration: none;
}

.resources-btn {
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.resources-btn-green {
    background-color: #2f7a2f;
    color: #fff;
}

.resources-btn-green:hover {
    background-color: #1e5c1e;
}

.resources-btn-yellow {
    background-color: #f4c542;
    color: #000;
}

.resources-btn-red {
    background-color: #e74c3c;
    color: #fff;
}

.resources-btn-gray {
    background-color: #bdc3c7;
    color: #fff;
}

/* Tables */
.resources-table-wrapper {
    margin-top: 1rem;
}

.resources-type-header {
    margin-top: 2rem;
    color: #2f7a2f;
    border-bottom: 2px solid #2f7a2f;
    padding-bottom: 0.2rem;
}

.resources-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.5rem;
}

.resources-table th, .resources-table td {
    padding: 0.8rem 1rem;
    text-align: left;
    border-bottom: 1px solid #d1e7d1;
}

.resources-status {
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    font-weight: 500;
    display: inline-block;
}

.resources-status.active { background-color: #2ecc71; color: #fff; }
.resources-status.unavailable { background-color: #e74c3c; color: #fff; }
.resources-status.maintenance { background-color: #f39c12; color: #fff; }
.resources-status.reserved { background-color: #3498db; color: #fff; }

/* Modal */
.resources-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(47, 122, 47, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 999;
}

.resources-modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    width: 500px;
    max-width: 95%;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.resources-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.resources-close-btn {
    background: transparent;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #2f7a2f;
}

.resources-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.resources-form-grid label {
    display: flex;
    flex-direction: column;
    font-weight: 500;
}

.resources-form-grid input,
.resources-form-grid select {
    margin-top: 0.3rem;
    padding: 0.5rem;
    border-radius: 6px;
    border: 1px solid #cfd8cf;
}

.resources-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1.5rem;
}
</style>

<div class="resources-container">

    <div class="task-header">
        <a href="javascript:history.back()" class="back-link">← Back</a>
        <h2>Resources Management</h2>
        <button class="resources-btn resources-btn-green" wire:click="openAddModal">+ Add Resource</button>
    </div>

    <div class="resources-table-wrapper">
        @if(count($resources))
            @foreach($groupedResources as $type => $resGroup)
                <h3 class="resources-type-header">{{ $type }}</h3>
                <table class="resources-table">
                    <thead>
                        <tr>
                            <th>Resource Name</th>
                            <th>Unit Cost</th>
                            <th>Available Qty</th>
                            <th>Status</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resGroup as $r)
                            <tr>
                                <td>{{ $r->resource_name }}</td>
                                <td>₱{{ number_format($r->unit_cost, 2) }}</td>
                                <td>{{ $r->availability_quantity ?? 0 }}</td>
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
            @endforeach
        @else
            <div class="resources-empty">No resources found.</div>
        @endif
    </div>

    <!-- Modal -->
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
                        <input type="text" wire:model="resource_name" placeholder="Enter resource name" required>
                    </label>

                    <label>
                        <span>Resource Type</span>
                        <select wire:model.live="type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Materials">Materials</option>
                            <option value="Labor">Labor</option>
                            <option value="Tool">Tool</option>
                        </select>
                    </label>

                    @if($type)
                        <label>
                            <span>Inventory Items (filtered by type)</span>
                            <select wire:model.live="inventory_id">
                                <option value="">-- Select from inventory (optional) --</option>
                                @foreach($filteredInventories as $inv)
                                    <option value="{{ $inv->id }}">{{ $inv->name }} (Qty: {{ $inv->quantity }})</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    <label>
                        <span>Unit Cost</span>
                        <input type="number" step="0.01" wire:model="unit_cost" required>
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
                    <button type="submit" class="resources-btn resources-btn-green">{{ $editing_id ? 'Update' : 'Save' }}</button>
                    <button type="button" wire:click="closeResourceModal" class="resources-btn resources-btn-gray">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
