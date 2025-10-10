<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id('journal_id');
            $table->date('date');
            $table->string('reference_no')->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('hr_employees', 'employee_id')->onDelete('cascade');
            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};

