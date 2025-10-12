<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\DB;

Volt::route('/', 'home')->name('home');

Volt::route('/projects/resources', 'projects.resources')->name('projects.resources');
Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');
Volt::route('/projects/progress', 'projects.progress')->name('projects.progress');


use Carbon\Carbon;

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
                        'start_date' => Carbon::parse($t->start_date)->format('d-m-Y'),
                        'end_date' => Carbon::parse($t->end_date)->format('d-m-Y'),
                        'progress' => $t->progress_percentage / 100,
                        'parent' => 'phase_'.$t->phase_id
                    ];
                });

            // Use phase DB dates if available, otherwise compute from tasks
            $phaseStart = $phase->start_date 
                ? Carbon::parse($phase->start_date)->format('d-m-Y') 
                : ($tasks->min(fn($t) => Carbon::createFromFormat('d-m-Y', $t['start_date']))->format('d-m-Y') ?? null);

            $phaseEnd = $phase->end_date 
                ? Carbon::parse($phase->end_date)->format('d-m-Y') 
                : ($tasks->max(fn($t) => Carbon::createFromFormat('d-m-Y', $t['end_date']))->format('d-m-Y') ?? null);

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

    // Use project DB dates if available, otherwise compute from phases
    $projectStart = $project->start_date 
        ? Carbon::parse($project->start_date)->format('d-m-Y') 
        : ($phases->min(fn($p) => Carbon::createFromFormat('d-m-Y', $p['start_date']))->format('d-m-Y') ?? null);

    $projectEnd = $project->end_date 
        ? Carbon::parse($project->end_date)->format('d-m-Y') 
        : ($phases->max(fn($p) => Carbon::createFromFormat('d-m-Y', $p['end_date']))->format('d-m-Y') ?? null);

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

    \Log::info('Gantt data: ' . json_encode($ganttData));
    return response()->json($ganttData);
});


require __DIR__.'/auth.php';

