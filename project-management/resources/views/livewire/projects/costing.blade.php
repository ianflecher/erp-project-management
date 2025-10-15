<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public ?int $taskId = null;
    public $task;
    public $costsByType = [];

    public function mount($task)
    {
        $this->taskId = $task;
        $this->task = DB::table('tasks')->where('task_id', $this->taskId)->first();

        // Fetch resource allocations
        $resources = DB::table('resource_allocations as ra')
            ->join('resources as r', 'r.resource_id', '=', 'ra.resource_id')
            ->where('ra.task_id', $this->taskId)
            ->select('r.type', 'r.resource_name', 'ra.allocated_quantity', 'ra.cost')
            ->get();

        // Group by type safely
        $this->costsByType = [];
        foreach(['Labor','Materials','Overhead','Tool','Facility'] as $type) {
            $this->costsByType[$type] = $resources->where('type', $type)->values();
        }
    }

    public function getTotalByType($type)
    {
        return isset($this->costsByType[$type]) 
            ? $this->costsByType[$type]->sum('cost') 
            : 0;
    }
}
?>


<div class="p-6">
    <h2 class="text-2xl font-bold text-green-700">💲 Actual Costs for Task: {{ $task->task_name ?? 'N/A' }}</h2>

    @foreach(['Labor', 'Materials', 'Overhead', 'Tool', 'Facility'] as $type)
        <div class="mt-4 p-4 bg-white rounded-lg shadow-sm">
            <h3 class="font-semibold text-gray-800">{{ $type }}</h3>
            @if(isset($costsByType[$type]) && $costsByType[$type]->isNotEmpty())
                <ul class="list-disc list-inside mt-2">
                    @foreach($costsByType[$type] as $item)
                        <li>{{ $item->resource_name }} — Qty: {{ $item->allocated_quantity }} | Cost: ₱{{ number_format($item->cost,2) }}</li>
                    @endforeach
                </ul>
                <p class="font-semibold mt-2">Total {{ $type }}: ₱{{ number_format($this->getTotalByType($type),2) }}</p>
            @else
                <p class="text-gray-500 mt-1">No {{ strtolower($type) }} costs recorded.</p>
            @endif
        </div>
    @endforeach

    <div class="mt-4 font-semibold text-lg">
        Total Task Cost: ₱{{ number_format(
            array_sum(array_map(fn($type) => $this->getTotalByType($type), ['Labor','Materials','Overhead','Tool','Facility'])), 2) }}
    </div>

    <a href="{{ route('projects.home') }}" class="mt-4 inline-block px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800">← Back to Project</a>
</div>
