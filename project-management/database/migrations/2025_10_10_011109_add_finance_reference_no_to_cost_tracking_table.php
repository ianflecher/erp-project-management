<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cost_tracking', function (Blueprint $table) {
            $table->string('finance_reference_no')->nullable()->after('notes');
            $table->foreign('finance_reference_no')->references('reference_no')->on('journal_entries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cost_tracking', function (Blueprint $table) {
            $table->dropForeign(['finance_reference_no']);
            $table->dropColumn('finance_reference_no');
        });
    }
};

