<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_monitoring', function (Blueprint $table) {
            if (Schema::hasColumn('kpi_monitoring', 'scope_changes')) {
                $table->dropColumn('scope_changes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpi_monitoring', function (Blueprint $table) {
            $table->integer('scope_changes')->default(0);
        });
    }
};
