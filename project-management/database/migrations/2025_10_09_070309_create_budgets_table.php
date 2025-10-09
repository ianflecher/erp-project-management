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
    Schema::create('budgets', function (Blueprint $table) {
        $table->increments('budget_id');
        $table->unsignedInteger('project_id');
        $table->string('phase_name');
        $table->decimal('estimated_cost', 15, 2)->default(0);
        $table->decimal('actual_cost', 15, 2)->default(0);
        $table->decimal('variance', 15, 2)->default(0);
        $table->timestamps();

        $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('budgets');
}

};
