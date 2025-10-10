<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\DB;

Volt::route('/', 'home')->name('home');

Volt::route('/projects/resources', 'projects.resources')->name('projects.resources');
Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');
Volt::route('/projects/progress', 'projects.progress')->name('projects.progress');



Route::get('/gantt-tasks/{projectId}', function ($projectId) {
    $tasks = DB::table('tasks')
        ->whereIn('phase_id', function($query) use ($projectId) {
            $query->select('phase_id')
                  ->from('project_phases')
                  ->where('project_id', $projectId);
        })
        ->get()
        ->map(function($t){
            return [
                'id' => $t->task_id,
                'text' => $t->task_name,
                'start_date' => $t->start_date,
                'end_date' => $t->end_date,
                'progress' => $t->progress_percentage / 100,
            ];
        });

    return response()->json($tasks);
});

require __DIR__.'/auth.php';

