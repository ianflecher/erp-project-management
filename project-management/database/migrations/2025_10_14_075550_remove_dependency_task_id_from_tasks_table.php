<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'dependency_task_id')) {
                $table->dropForeign(['dependency_task_id']); // optional, only if foreign key exists
                $table->dropColumn('dependency_task_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('dependency_task_id')->nullable()->after('progress_percentage');
            // add back the foreign key if it existed
            // $table->foreign('dependency_task_id')->references('task_id')->on('tasks')->onDelete('set null');
        });
    }
};
