<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')]
class extends Component
{
    public int $phase_id;
    public string $phase_name; // store phase name
    public $estimatedCost;

    public function mount(int $phase_id)
    {
        $this->phase_id = $phase_id;

        $phase = DB::table('project_phases')->where('phase_id', $phase_id)->first();
        $this->phase_name = $phase ? $phase->phase_name : 'Unknown Phase';
    }

    public function saveBudget()
    {
        $this->validate([
            'estimatedCost' => 'required|numeric|min:0',
        ]);

        $phase = DB::table('project_phases')->where('phase_id', $this->phase_id)->first();

DB::table('budgets')->insert([
    'project_id' => $phase->project_id,   // add this
    'phase_id' => $this->phase_id,
    'estimated_cost' => $this->estimatedCost,
    'created_at' => now(),
    'updated_at' => now(),
]);


        session()->flash('success', 'Budget saved successfully!');
        $this->estimatedCost = null;

        return redirect()->route('projects.budget');
    }

    public function goBack()
{
    return redirect()->route('projects.budget');
}

}
?>
<div class="phase-container">

    <!-- Header with Back Button -->
    <div class="phase-header">
    <h2>Add Budget for {{ $phase_name }}</h2>
    <button 
        wire:click="goBack" 
        type="button"
        class="back-link">
        ← Back
    </button>
</div>


    <!-- Success Message -->
    @if (session()->has('success'))
        <p style="color:green; margin-bottom:1rem;">{{ session('success') }}</p>
    @endif

    <!-- Budget Form -->
    <form wire:submit.prevent="saveBudget" style="margin-bottom:2rem;">
        <label for="estimatedCost" style="display:block; margin-bottom:0.5rem; font-weight:600;">Estimated Cost</label>
        <input 
            id="estimatedCost" 
            type="number" 
            step="0.01" 
            wire:model="estimatedCost" 
            style="width:100%; padding:0.5rem; border:1px solid #e5e7eb; border-radius:6px; margin-bottom:1rem;"
        >
        @error('estimatedCost')
            <p style="color:red; margin-bottom:1rem;">{{ $message }}</p>
        @enderror

        <button type="submit" class="phase-btn phase-btn-green">
            💾 Save Budget
        </button>
    </form>

    <!-- Optional: Table of Existing Budgets -->
    @if(isset($budgets) && count($budgets) > 0)
    <div class="phase-table-container">
        <table class="phase-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Estimated Cost</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($budgets as $budget)
                    <tr>
                        <td>{{ $budget->id }}</td>
                        <td>{{ number_format($budget->estimated_cost, 2) }}</td>
                        <td>{{ $budget->created_at }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <p class="phase-no-data">No budget records found.</p>
    @endif

</div>
