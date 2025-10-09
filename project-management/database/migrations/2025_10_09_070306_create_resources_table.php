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
    Schema::create('resources', function (Blueprint $table) {
        $table->increments('resource_id');
        $table->string('resource_name');
        $table->string('type');
        $table->decimal('unit_cost', 10, 2)->default(0);
        $table->decimal('availability_quantity', 10, 2)->default(0);
        $table->string('status');
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('resources');
}

};
