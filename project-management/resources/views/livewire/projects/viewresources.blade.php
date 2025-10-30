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

    // Helpers
    public bool $isLabor = false;
    public array $filteredInventories = [];

    public function mount()
    {
        $this->loadResources();
        $this->inventories = DB::table('inventories')->orderBy('name')->get()->toArray();
        $this->filteredInventories = $this->inventories; // default
    }

    /**
     * When resource type changes:
     * - determine if it is labor
     * - compute filteredInventories to display appropriate dropdown items
     * - reset inventory selection/qty when switching to Labor
     */
    public function updatedType($value)
    {
        $this->type = $value;
        $this->isLabor = (strtolower($value) === 'labor');

        // Build filteredInventories according to chosen type
        $this->filteredInventories = array_values(array_filter($this->inventories, function ($inv) use ($value) {
            $cat = strtolower(trim((string)($inv->category ?? '')));
            $val = strtolower(trim((string)$value));

            if ($val === 'labor') {
                return $cat === 'labor';
            }

            if ($val === 'tool') {
                // include inventory categories that explicitly match tool or contain 'tool'
                return $cat === 'tool' || str_contains($cat, 'tool');
            }

            if ($val === 'materials') {
                // materials = everything that is not labor or tool (catch-all)
                return ($cat !== 'labor') && !str_contains($cat, 'tool');
            }

            // fallback: match category to selected value
            return $cat === $val;
        }));

        // Reset inventory/qty if labor, otherwise if inventory_id exists try to refresh qty
        if ($this->isLabor) {
            $this->inventory_id = null;
            $this->availability_quantity = 0;
        } else {
            if ($this->inventory_id) {
                $inv = collect($this->inventories)->firstWhere('id', $this->inventory_id);
                $this->availability_quantity = $inv->quantity ?? 0;
                $this->resource_name = $inv->name ?? $this->resource_name;
            }
        }
    }

    /**
     * Livewire hook when inventory_id changes.
     * Auto-fill name + availability quantity for non-labor types.
     */
    public function updatedInventory_id($value)
    {
        // If no selection or labor, nothing to do
        if (!$value) {
            if ($this->isLabor) {
                $this->availability_quantity = 0;
            }
            return;
        }

        $inv = collect($this->inventories)->firstWhere('id', $value);

        if ($inv) {
            $this->resource_name = $inv->name ?? $this->resource_name;

            // If the selected type is Labor, we still set qty to 0 (you store labor qty as 0)
            if ($this->isLabor) {
                $this->availability_quantity = 0;
            } else {
                $this->availability_quantity = $inv->quantity ?? 0;
            }
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

            // ensure filteredInventories is updated for the current type
            $this->updatedType($this->type);

            if (!$this->isLabor && $this->inventory_id) {
                $inv = collect($this->inventories)->firstWhere('id', $this->inventory_id);
                $this->availability_quantity = $inv->quantity ?? 0;
                $this->resource_name = $inv->name ?? $res->resource_name;
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
        // Basic validation (you can expand with Validator if needed)
        if (!$this->resource_name || !$this->type || !$this->status) {
            // you could throw validation error or return
        }

        $data = [
            'resource_name' => $this->resource_name,
            'type' => $this->type,
            'unit_cost' => $this->unit_cost,
            // store availability_quantity as 0 for labor
            'availability_quantity' => $this->isLabor ? 0 : $this->availability_quantity,
            'status' => $this->status,
            'inventory_id' => $this->inventory_id, // keep whatever is selected
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
<!-- ====== Blade UI (same file) ====== -->
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
                            <td>{{ $r->inventory_name ?? '—' }} {{ $r->inventory_name ? '(Qty: '.$r->inventory_qty.')' : '' }}</td>
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

                    <!-- 1) Resource Type (choose first) -->
                    <label style="grid-column:1 / span 2;">
                        <span>Resource Type</span>
                        <select wire:model.live="type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Materials">Materials</option>
                            <option value="Labor">Labor</option>
                            <option value="Tool">Tool</option>
                        </select>
                    </label>

                    <!-- 2) Inventory dropdown: appears after type selected and is filtered -->
                    @if($type)
                        <label style="grid-column:1 / span 2;">
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

                    <!-- Show quantity only when not labor -->
                    @if(!$isLabor)
                        <label>
                            <span>Available Quantity</span>
                            <input type="number" wire:model="availability_quantity" readonly>
                        </label>
                    @endif

                    <label style="grid-column:1 / span 2;">
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

                <div class="resources-modal-actions" style="margin-top:1rem;">
                    <button type="submit" class="resources-btn resources-btn-green">{{ $editing_id ? 'Update' : 'Save' }}</button>
                    <button type="button" wire:click="closeResourceModal" class="resources-btn resources-btn-gray">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
