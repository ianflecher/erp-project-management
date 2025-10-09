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
    Schema::create('project_phases', function (Blueprint $table) {
        $table->increments('phase_id');
        $table->unsignedInteger('project_id');
        $table->string('phase_name');
        $table->text('description')->nullable();
        $table->date('start_date');
        $table->date('end_date');
        $table->string('status');
        $table->timestamps();

        $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('project_phases');
}

};
