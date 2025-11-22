<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.finance')] class extends Component
{
    public $pendingApprovals;
    public $approvedBudgets;
    public $totalBudgetAmount;
    public $totalProjects;

    public function mount()
    {
        $this->pendingApprovals = DB::table('budget_approvals')
            ->where('status', 'pending')
            ->count();

        $this->approvedBudgets = DB::table('budget_approvals')
            ->where('status', 'approved')
            ->count();

        $this->totalBudgetAmount = DB::table('budgets')
            ->sum('estimated_cost');

        $this->totalProjects = DB::table('projects')->count();
    }
};
?>

<style>
.finance-dashboard {
    padding: 2rem;
    font-family: "Segoe UI", sans-serif;
    background: #f0fff4; /* soft mint background */
    min-height: 100vh;
}

.dashboard-title {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 1.5rem;
    color: #14532d; /* dark green */
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Green Stat Box Theme */
.stat-box {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 80, 20, 0.08);
    border-left: 6px solid #16a34a; /* Green accent */
}

.stat-box h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: .5rem;
    color: #166534; /* medium green */
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #0f3d1d; /* dark green */
}

.dashboard-section {
    margin-top: 2rem;
    background: #e8f5e9; /* pale green */
    border-radius: 10px;
    padding: 1.5rem;
}
</style>

<!-- DASHBOARD UI -->
<div class="finance-dashboard">

    <h1 class="dashboard-title">Finance Dashboard</h1>

    <!-- Stat Boxes -->
    <div class="stat-grid">

        <div class="stat-box">
            <h3>Pending Approvals</h3>
            <p class="stat-number">{{ $pendingApprovals }}</p>
        </div>

        <div class="stat-box">
            <h3>Approved Budgets</h3>
            <p class="stat-number">{{ $approvedBudgets }}</p>
        </div>

        <div class="stat-box">
            <h3>Total Budget Value</h3>
            <p class="stat-number">₱{{ number_format($totalBudgetAmount, 2) }}</p>
        </div>

        <div class="stat-box">
            <h3>Total Projects</h3>
            <p class="stat-number">{{ $totalProjects }}</p>
        </div>

    </div>

</div>
