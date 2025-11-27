<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.finance')] class extends Component
{
    public $pendingBudgets = [];

    protected $listeners = [
        'approveBudget' => 'approve',
        'declineBudget' => 'decline',
    ];

    public function mount()
    {
        $this->loadPendingBudgets();
    }

    public function loadPendingBudgets()
{
    try {
        $this->pendingBudgets = DB::table('budget_approvals')
            ->join('budgets', 'budget_approvals.budget_id', '=', 'budgets.budget_id')
            ->join('projects', 'budgets.project_id', '=', 'projects.project_id')
            ->join('users', 'budget_approvals.requested_by', '=', 'users.id')
            ->leftJoin('project_phases', 'budgets.phase_id', '=', 'project_phases.phase_id')
            ->select(
                'budget_approvals.approval_id',
                'projects.project_name',
                DB::raw('COALESCE(project_phases.phase_name, "Project Budget") as phase_name'),
                'budgets.estimated_cost',
                'users.name as requested_by'
            )
            ->where('budget_approvals.status', 'pending')
            ->get()
            ->toArray();
            
        logger('Query successful. Found: ' . count($this->pendingBudgets) . ' records');
        
    } catch (\Exception $e) {
        logger('Error loading pending budgets: ' . $e->getMessage());
        $this->pendingBudgets = [];
    }
}

    public function approve($approvalId)
{
    // Update the budget approval
    DB::table('budget_approvals')->where('approval_id', $approvalId)->update([
        'status' => 'Approved',
        'approved_at' => now(),
        'reviewed_by' => auth()->id(),
    ]);

    // Get the budget to find the related project
    $budget = DB::table('budget_approvals')
        ->join('budgets', 'budget_approvals.budget_id', '=', 'budgets.budget_id')
        ->where('budget_approvals.approval_id', $approvalId)
        ->select('budgets.project_id')
        ->first();

    if ($budget) {
        DB::table('projects')->where('project_id', $budget->project_id)
            ->update(['status' => 'Pending', 'updated_at' => now()]);
    }

    // Reload pending budgets
    $this->loadPendingBudgets();
}


    public function decline($approvalId)
    {
        DB::table('budget_approvals')->where('approval_id', $approvalId)->update([
            'status' => 'Rejected',
            'approved_at' => null,
            'reviewed_by' => auth()->id(),
        ]);

        $this->loadPendingBudgets();
    }

    

};
?>


<style>
.approval-container {
    padding: 2rem;
    font-family: "Segoe UI", sans-serif;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #14532d; /* dark green */
}

/* Table Styling */
.approval-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 90, 40, 0.15);
}

.approval-table th {
    background: #16a34a;
    color: white;
    padding: 14px;
    font-size: 14px;
    text-align: left;
}

.approval-table td {
    padding: 14px;
    border-bottom: 1px solid #e2e8f0;
}

.action-btn {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s;
}

.btn-approve {
    background: #22c55e;
    color: white;
}

.btn-approve:hover {
    background: #16a34a;
}

.btn-decline {
    background: #dc2626;
    color: white;
}

.btn-decline:hover {
    background: #b91c1c;
}
</style>
<div class="approval-container">
    <h1 class="page-title">Budget Approval Center</h1>

    @if(count($pendingBudgets) === 0)
        <p style="color:#166534; font-weight:600;">No pending budget approvals.</p>
    @else
        <table class="approval-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Phase</th>
                    <th>Estimated Cost</th>
                    <th>Requested By</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @foreach($pendingBudgets as $item)
                <tr>
                    <td>{{ $item->project_name }}</td>
                    <td>{{ $item->phase_name }}</td>
                    <td>₱{{ number_format($item->estimated_cost, 2) }}</td>
                    <td>{{ $item->requested_by }}</td>
                    <td>
    <!-- View Button -->
    <a href="{{ route('budgetdetails', ['id' => $item->approval_id]) }}"
       class="action-btn"
       style="background:#0284c7; color:white; margin-right:6px;">
        View
    </a>

    <!-- Approve Button -->
    <button class="action-btn btn-approve"
        onclick="if(confirm('Approve this budget?')) { @this.approve({{ $item->approval_id }}).then(() => location.reload()) }">
        Approve
    </button>

    <!-- Decline Button -->
    <button class="action-btn btn-decline"
        onclick="if(confirm('Decline this budget?')) { @this.decline({{ $item->approval_id }}).then(() => location.reload()) }">
        Decline
    </button>
</td>




                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<script>
    document.addEventListener('livewire:load', function () {
        Livewire.on('confirmAction', (id, action) => {
            let msg = action === 'approve' ? 'Approve this budget?' : 'Decline this budget?';
            if (confirm(msg)) {
                Livewire.emit(action + 'Budget', id); // calls PHP approveBudget / declineBudget
            }
        });
    });
</script>

