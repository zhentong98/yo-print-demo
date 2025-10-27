<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_data', function (Blueprint $table) {
            $table->integer('csv_occurrence_count')->default(1)->after('unique_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_data', function (Blueprint $table) {
            $table->dropColumn('csv_occurrence_count');
        });
    }
};
