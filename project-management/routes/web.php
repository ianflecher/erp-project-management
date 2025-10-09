<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'home')->name('home');

Volt::route('/projects/resources', 'projects.resources')->name('projects.resources');
Volt::route('/projects/budget', 'projects.budget')->name('projects.budget');
Volt::route('/projects/progress', 'projects.progress')->name('projects.progress');

require __DIR__.'/auth.php';

