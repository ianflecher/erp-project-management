<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Add project_member_id column after project_manager_id
            $table->unsignedBigInteger('project_member_id')->nullable()->after('project_manager_id');

            // Add foreign key constraint referencing hr_employees
            $table->foreign('project_member_id')
                  ->references('employee_id')
                  ->on('hr_employees')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_member_id']);
            $table->dropColumn('project_member_id');
        });
    }
};
