<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('phase_name'); // remove old column
            $table->unsignedInteger('phase_id')->after('project_id'); // add new column
            $table->foreign('phase_id')->references('phase_id')->on('project_phases')->onDelete('cascade');
        });
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


