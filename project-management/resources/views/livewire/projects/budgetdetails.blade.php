<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public $approvalId;
    public $details = null;

    public $showRevision = false;
    public $revisionRemarks = '';

    public function mount($id)
    {
        $this->approvalId = $id;
        $this->loadDetails();
    }

    public function loadDetails()
    {
        $this->details = DB::table('budget_approvals')
            ->join('budgets', 'budget_approvals.budget_id', '=', 'budgets.budget_id')
            ->join('projects', 'budgets.project_id', '=', 'projects.project_id')
            ->leftJoin('project_phases', 'budgets.phase_id', '=', 'project_phases.phase_id')
            ->join('users', 'budget_approvals.requested_by', '=', 'users.id')
            ->where('budget_approvals.approval_id', $this->approvalId)
            ->select(
                'budget_approvals.*',
                'projects.project_name',
                DB::raw('COALESCE(project_phases.phase_name, "Project Budget") as phase_name'),
                'budgets.estimated_cost',
                'users.name as requested_by'
            )
            ->first();
    }

    public function submitRevision()
{
    DB::table('budget_approvals')
        ->where('approval_id', $this->approvalId)
        ->update([
            'remarks' => $this->revisionRemarks,
            'status' => 'revision',
            'reviewed_by' => auth()->id(),
            'updated_at' => now(),
        ]);

    // Redirect back to the budget list page
    return redirect()->route('managebudget');
}

};
?>

<div class="p-6 bg-white shadow-lg rounded-xl">

    <div class="task-header" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="javascript:history.back()" class="back-link">← Back</a>
        <h2 style="font-size:22px;font-weight:700;color:#14532d;">Budget Details</h2>
    </div>

    @if(!$details)
        <p>Loading details...</p>
    @else
        <div style="margin-top:20px; line-height:1.8;">

            <p><strong>Project</strong> {{ $details->project_name }}</p>
            <p><strong>Phase</strong> {{ $details->phase_name }}</p>
            <p><strong>Estimated Cost</strong> ₱{{ number_format($details->estimated_cost, 2) }}</p>
            <p><strong>Requested By</strong> {{ $details->requested_by }}</p>
            <p><strong>Status</strong> {{ $details->status }}</p>

            @if($details->remarks)
                <p style="margin-top:15px;"><strong>Remarks</strong> {{ $details->remarks }}</p>
            @endif
        </div>

        <!-- Revision Button -->
        <div style="margin-top:20px;">
            <button wire:click="$set('showRevision', true)"
                style="padding:10px 16px;background:#dc2626;color:white;border-radius:8px;font-weight:600;">
                Send for Revision
            </button>
        </div>

        <!-- Revision Text Input -->
        @if($showRevision)
        <div style="margin-top:18px; padding:12px; border:1px solid #ccc; border-radius:10px;">

            <label style="font-weight:600;">Revision Remarks</label>
            <textarea wire:model="revisionRemarks"
                style="width:100%;height:120px;border:1px solid #ccc;border-radius:8px;padding:10px;margin-top:6px;"></textarea>

            <button wire:click="submitRevision"
                style="margin-top:10px;padding:10px 16px;background:#14532d;color:white;border-radius:8px;font-weight:600;">
                Submit Revision
            </button>
        </div>
        @endif

        @if (session()->has('message'))
            <p style="margin-top:12px;color:#16a34a;font-weight:600;">{{ session('message') }}</p>
        @endif

    @endif
</div>
