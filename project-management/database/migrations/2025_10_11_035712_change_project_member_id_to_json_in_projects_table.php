<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop index if it exists
        $indexes = DB::select("SHOW INDEX FROM `projects` WHERE Column_name = 'project_member_id'");
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE `projects` DROP INDEX `{$index->Key_name}`");
        }

        // Now change the column to JSON
        Schema::table('projects', function (Blueprint $table) {
    $table->json('project_member_id')->nullable()->change();
});

    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_member_id', 255)->nullable()->change();
        });
    }
};
