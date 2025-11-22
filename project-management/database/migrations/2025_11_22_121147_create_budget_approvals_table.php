<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('budget_approvals', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('budget_id');   // ID of the budget entry
        $table->unsignedBigInteger('requested_by'); // user who requested
        $table->unsignedBigInteger('approved_by')->nullable(); // finance approver

        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->text('remarks')->nullable();

        $table->timestamps();

        // Foreign Keys
        $table->foreign('requested_by')
              ->references('id')
              ->on('users')
              ->onDelete('cascade');

        $table->foreign('approved_by')
              ->references('id')
              ->on('users')
              ->onDelete('set null');
    });
}


    public function down(): void
    {
        Schema::dropIfExists('budget_approvals');
    }
};
