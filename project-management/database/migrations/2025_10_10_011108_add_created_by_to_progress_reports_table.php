<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
{
    Schema::table('progress_reports', function (Blueprint $table) {
        if (!Schema::hasColumn('progress_reports', 'created_by')) {
            $table->foreignId('created_by')
                  ->constrained('hr_employees', 'employee_id')
                  ->onDelete('set null')
                  ->nullable();
        }
    });
}


    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};

