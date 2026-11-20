<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per page bundle file (src/data/pages/<slug>.json)
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->longText('source_json');          // the original file, byte for byte, as last imported
            $table->string('source_sha256', 64);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        // One row per HTML fragment inside a bundle ({"<block_key>": "<html>"})
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('block_key');
            $table->unsignedInteger('position');
            $table->longText('html');
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['page_id', 'block_key']);
            $table->index(['page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
        Schema::dropIfExists('pages');
    }
};
