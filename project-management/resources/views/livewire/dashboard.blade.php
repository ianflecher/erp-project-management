<?php

namespace App\Http\Livewire\Volt;

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component
{
    public array $projects = [];
    public int $totalProjects = 0;
    public int $ongoingProjects = 0;
    public float $totalBudget = 0.0;

    public function mount()
    {
        $this->projects = DB::table('projects')->get()->toArray();

        $this->totalProjects = count($this->projects);
        $this->ongoingProjects = collect($this->projects)
            ->where('status', '!=', 'Completed')
            ->count();

        $this->totalBudget = collect($this->projects)
            ->sum('budget_total');
    }
}
?>
<div class="dashboard">
    <!-- Dashboard Cards -->
    <div class="cards">
        <div class="card card-green">
            <h2>Total Projects</h2>
            <p>{{ $totalProjects }}</p>
        </div>
        <div class="card card-yellow">
            <h2>Ongoing Projects</h2>
            <p>{{ $ongoingProjects }}</p>
        </div>
        <div class="card card-blue">
            <h2>Total Budget</h2>
            <p>₱{{ number_format($totalBudget, 2) }}</p>
        </div>
        <div class="card card-purple">
            <h2>Completed Projects</h2>
            <p>{{ $totalProjects - $ongoingProjects }}</p>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="table-container">
        <table class="projects-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project Name</th>
                    <th>Description</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>Manager ID</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td>{{ $project->project_id }}</td>
                        <td>{{ $project->project_name }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($project->description, 50) }}</td>
                        <td>{{ $project->start_date }}</td>
                        <td>{{ $project->end_date }}</td>
                        <td>{{ $project->status }}</td>
                        <td>₱{{ number_format($project->budget_total, 2) }}</td>
                        <td>{{ $project->project_manager_id }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

