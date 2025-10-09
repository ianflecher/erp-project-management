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
    Schema::create('progress_reports', function (Blueprint $table) {
        $table->increments('report_id');
        $table->unsignedInteger('project_id');
        $table->date('report_date');
        $table->decimal('overall_progress', 5, 2)->default(0);
        $table->text('issues')->nullable();
        $table->text('next_steps')->nullable();
        $table->unsignedInteger('created_by');
        $table->timestamps();

        $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('progress_reports');
}

};
