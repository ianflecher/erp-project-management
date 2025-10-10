<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        if (!Schema::hasColumn('tasks', 'assigned_to')) {
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('hr_employees', 'employee_id')
                  ->after('dependency_task_id')
                  ->onDelete('set null');
        }
    });
}


    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
        });
    }
};

