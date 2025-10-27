<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_upload_id')
                  ->constrained('file_uploads')
                  ->onDelete('cascade');
            
            // CSV Fields from the requirements
            $table->string('unique_key')->unique();
            $table->string('product_title');
            $table->text('product_description')->nullable();
            $table->string('style_number')->nullable();
            $table->string('sanmar_mainframe_color')->nullable();
            $table->string('size')->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('piece_price', 10, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('file_upload_id');
            $table->index('unique_key');
            $table->index('style_number');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_data');
    }
};
