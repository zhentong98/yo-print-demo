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
            // Drop index first (for SQLite compatibility)
            $table->dropIndex('product_data_file_upload_id_index');
        });

        Schema::table('product_data', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['file_upload_id']);
        });

        Schema::table('product_data', function (Blueprint $table) {
            // Drop the column
            $table->dropColumn('file_upload_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_data', function (Blueprint $table) {
            $table->foreignId('file_upload_id')
                ->after('id')
                ->constrained('file_uploads')
                ->onDelete('cascade');
        });
    }
};
