<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Drop the old project_id foreign key if it exists
            if (Schema::hasColumn('tasks', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            }

            // Add phase_id foreign key (if not already present)
            if (!Schema::hasColumn('tasks', 'phase_id')) {
    $table->foreignId('phase_id')
          ->nullable()
          ->constrained('project_phases', 'phase_id')
          ->onDelete('cascade');
} else {
    $table->foreign('phase_id', 'fk_tasks_phase')
          ->references('phase_id')
          ->on('project_phases')
          ->onDelete('cascade');
}

        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
            $table->dropColumn('phase_id');

            $table->foreignId('project_id')
                  ->constrained('projects', 'project_id')
                  ->onDelete('cascade');
        });
    }
};


