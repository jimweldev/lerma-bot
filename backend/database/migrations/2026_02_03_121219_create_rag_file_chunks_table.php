<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('rag_file_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_file_id')->constrained('rag_files')->onDelete('cascade');
            $table->text('content');
            $table->integer('chunk_index');
            $table->integer('token_count')->nullable();
            $table->json('metadata')->nullable(); // for page numbers, headings, etc.
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['rag_file_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('rag_file_chunks');
    }
};
