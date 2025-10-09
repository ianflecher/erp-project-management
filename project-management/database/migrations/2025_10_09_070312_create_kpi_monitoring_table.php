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
    Schema::create('kpi_monitoring', function (Blueprint $table) {
        $table->increments('kpi_id');
        $table->unsignedInteger('project_id');
        $table->decimal('time_variance', 10, 2)->default(0);
        $table->decimal('cost_variance', 10, 2)->default(0);
        $table->integer('scope_changes')->default(0);
        $table->timestamp('last_updated')->useCurrent();
        $table->timestamps();

        $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('kpi_monitoring');
}

};
