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
    Schema::create('alerts', function (Blueprint $table) {
        $table->increments('alert_id');
        $table->unsignedInteger('project_id');
        $table->string('alert_type');
        $table->text('description')->nullable();
        $table->timestamp('alert_date')->useCurrent();
        $table->boolean('resolved')->default(false);
        $table->timestamps();

        $table->foreign('project_id')->references('project_id')->on('projects')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('alerts');
}

};
