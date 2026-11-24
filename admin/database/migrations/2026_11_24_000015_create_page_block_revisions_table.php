<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per saved change to a page part, kept so Undo still works for 24 hours after Save.
        Schema::create('page_block_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_block_id')->constrained('page_blocks')->cascadeOnDelete();
            $table->longText('html');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['page_block_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_block_revisions');
    }
};
