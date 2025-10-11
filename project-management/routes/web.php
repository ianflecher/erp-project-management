<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\DB;

Volt::route('/', 'home')->name('home');

Volt::route('/projects/resources', 'projects.resources')->name('projects.resources');
Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');
Volt::route('/projects/progress', 'projects.progress')->name('projects.progress');



Route::get('/gantt-tasks/{projectId}', function ($projectId) {
    // Get the project
    $project = DB::table('projects')
        ->where('project_id', $projectId)
        ->first();

    // Get project phases
    $phases = DB::table('project_phases')
        ->where('project_id', $projectId)
        ->get()
        ->map(function($phase) {
            // Get tasks for this phase
            $tasks = DB::table('tasks')
                ->where('phase_id', $phase->phase_id)
                ->get()
                ->map(function($t) use ($phase) {
                    return [
                        'id' => 'task_'.$t->task_id,
                        'text' => $t->task_name,
                        'start_date' => $t->start_date,
                        'end_date' => $t->end_date,
                        'progress' => $t->progress_percentage / 100,
                        'parent' => 'phase_'.$phase->phase_id
                    ];
                });

            return [
                'id' => 'phase_'.$phase->phase_id,
                'text' => $phase->phase_name,
                'start_date' => $phase->start_date,
                'end_date' => $phase->end_date,
                'progress' => 0,
                'parent' => 'project_'.$phase->project_id,
                'children' => $tasks
            ];
        });

    // Build Gantt data
    $ganttData = [];

    // Add project as top-level
    $ganttData[] = [
        'id' => 'project_'.$project->project_id,
        'text' => $project->project_name,
        'start_date' => $project->start_date,
        'end_date' => $project->end_date,
        'open' => true,
        'progress' => 0
    ];

    // Add phases and tasks
    foreach ($phases as $phase) {
        $ganttData[] = [
            'id' => $phase['id'],
            'text' => $phase['text'],
            'start_date' => $phase['start_date'],
            'end_date' => $phase['end_date'],
            'parent' => $phase['parent'],
            'open' => true,
            'progress' => $phase['progress']
        ];

        foreach ($phase['children'] as $task) {
            $ganttData[] = $task;
        }
    }

    return response()->json($ganttData);
});


require __DIR__.'/auth.php';

