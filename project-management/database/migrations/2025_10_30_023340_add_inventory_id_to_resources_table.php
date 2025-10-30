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
    Schema::table('resources', function (Blueprint $table) {
        $table->unsignedBigInteger('inventory_id')->nullable()->after('resource_id');

        $table->foreign('inventory_id')
            ->references('id')
            ->on('inventories')
            ->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('resources', function (Blueprint $table) {
        $table->dropForeign(['inventory_id']);
        $table->dropColumn('inventory_id');
    });
}


};
