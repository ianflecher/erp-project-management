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
    Schema::create('resource_allocations', function (Blueprint $table) {
        $table->increments('allocation_id');
        $table->unsignedInteger('task_id');
        $table->unsignedInteger('resource_id');
        $table->decimal('allocated_quantity', 10, 2)->default(0);
        $table->date('allocation_date');
        $table->decimal('cost', 15, 2)->default(0);
        $table->timestamps();

        $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
        $table->foreign('resource_id')->references('resource_id')->on('resources')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('resource_allocations');
}

};
