<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['project_member_id']);

            // Change the column type to string
            $table->string('project_member_id', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Revert to unsigned integer and make it non-null
            $table->unsignedInteger('project_member_id')->nullable(false)->change();

            // Re-add foreign key
            $table->foreign('project_member_id')->references('employee_id')->on('hr_employees')->onDelete('cascade');
        });
    }
};
