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
    Schema::create('cost_tracking', function (Blueprint $table) {
        $table->increments('cost_id');
        $table->unsignedInteger('task_id');
        $table->string('cost_type');
        $table->decimal('amount', 15, 2)->default(0);
        $table->date('date_incurred');
        $table->string('reference_no')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('cost_tracking');
}

};
