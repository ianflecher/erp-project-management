<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('budgets', 'phase_name')) {
        $table->dropColumn('phase_name');
    }

    // Add new column only if it doesn't exist
    if (!Schema::hasColumn('budgets', 'phase_id')) {
        $table->unsignedInteger('phase_id')->after('project_id');
        $table->foreign('phase_id')->references('phase_id')->on('project_phases')->onDelete('cascade');
    }

    
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('phase_name')->after('project_id'); // rollback
            $table->dropForeign(['phase_id']);
            $table->dropColumn('phase_id');
        });
    }
};


