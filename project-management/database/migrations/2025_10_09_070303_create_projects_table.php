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
    Schema::create('projects', function (Blueprint $table) {
        $table->increments('project_id');
        $table->string('project_name');
        $table->text('description')->nullable();
        $table->date('start_date');
        $table->date('end_date');
        $table->string('status');
        $table->decimal('budget_total', 15, 2)->default(0);
        $table->unsignedInteger('project_manager_id');
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('projects');
}

};
