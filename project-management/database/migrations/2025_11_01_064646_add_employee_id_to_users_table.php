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
    Schema::table('users', function (Blueprint $table) {
        // Add employee_id column
        $table->unsignedBigInteger('employee_id')->nullable()->after('id');

        // Drop role column
        if (Schema::hasColumn('users', 'role')) {
            $table->dropColumn('role');
        }

        // Add foreign key
        $table->foreign('employee_id')
            ->references('employee_id')
            ->on('hr_employees')
            ->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        // Drop foreign key & employee_id
        $table->dropForeign(['employee_id']);
        $table->dropColumn('employee_id');

        // Restore role column if rolling back
        $table->string('role')->nullable();
    });
}

};
