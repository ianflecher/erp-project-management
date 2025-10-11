<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\DB;

Volt::route('/', 'home')->name('home');

Volt::route('/projects/resources', 'projects.resources')->name('projects.resources');
Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');
Volt::route('/projects/progress', 'projects.progress')->name('projects.progress');

Route::get('/gantt-tasks/{projectId}', function ($projectId) {
    $project = DB::table('projects')->where('project_id', $projectId)->first();

    $phases = DB::table('project_phases')
        ->where('project_id', $projectId)
        ->get()
        ->map(function($phase) {
            $tasks = DB::table('tasks')
                ->where('phase_id', $phase->phase_id)
                ->get()
                ->map(function($t){
                    return [
    'id' => 'task_'.$t->task_id,
    'text' => $t->task_name,
    'start_date' => date('d-m-Y', strtotime($t->start_date)),
    'end_date' => date('d-m-Y', strtotime($t->end_date)),
    'progress' => $t->progress_percentage / 100,
    'parent' => 'phase_'.$t->phase_id
];

                });

            // Compute phase start/end from tasks
            $phaseStart = $tasks->min(fn($t) => $t['start_date']);
            $phaseEnd   = $tasks->max(fn($t) => $t['end_date']);

            return [
                'id' => 'phase_'.$phase->phase_id,
                'text' => $phase->phase_name,
                'start_date' => $phaseStart,
                'end_date' => $phaseEnd,
                'open' => true,
                'parent' => 'project_'.$phase->project_id,
                'tasks' => $tasks
            ];
        });

    // Compute project start/end from phases
    $projectStart = $phases->min(fn($p) => $p['start_date']);
    $projectEnd   = $phases->max(fn($p) => $p['end_date']);

    $ganttData = [
        [
            'id' => 'project_'.$project->project_id,
            'text' => $project->project_name,
            'start_date' => $projectStart,
            'end_date' => $projectEnd,
            'open' => true
        ]
    ];

    // Flatten phases and tasks
    foreach($phases as $phase) {
        $ganttData[] = [
            'id' => $phase['id'],
            'text' => $phase['text'],
            'start_date' => $phase['start_date'],
            'end_date' => $phase['end_date'],
            'open' => true,
            'parent' => $phase['parent']
        ];

        foreach($phase['tasks'] as $task) {
            $ganttData[] = $task;
        }
    }

    return response()->json($ganttData);
});

require __DIR__.'/auth.php';

