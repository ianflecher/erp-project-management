<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            // Add the new nullable foreign key column
            $table->unsignedBigInteger('task_id')->nullable()->after('phase_id');

            // If you have a tasks table, you can also define a foreign key constraint:
            // (Uncomment the next line if 'tasks' table exists)
            // $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            // Drop the foreign key first (if you added one)
            // $table->dropForeign(['task_id']);
            $table->dropColumn('task_id');
        });
    }
};
