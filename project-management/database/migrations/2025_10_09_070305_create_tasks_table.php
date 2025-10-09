<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('tasks', function (Blueprint $table) {
        $table->increments('task_id');
        $table->unsignedInteger('project_id');
        $table->unsignedInteger('phase_id')->nullable();
        $table->string('task_name');
        $table->text('description')->nullable();
        $table->date('start_date');
        $table->date('end_date');
        $table->string('status');
        $table->decimal('progress_percentage', 5, 2)->default(0);
        $table->unsignedInteger('dependency_task_id')->nullable();
        $table->unsignedInteger('assigned_to')->nullable();
        $table->timestamps();

        $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
        $table->foreign('phase_id')->references('phase_id')->on('project_phases')->onDelete('set null');
        $table->foreign('dependency_task_id')->references('task_id')->on('tasks')->onDelete('set null');
    });
}

public function down()
{
    Schema::dropIfExists('tasks');
}

};
