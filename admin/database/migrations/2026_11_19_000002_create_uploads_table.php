<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type')->nullable();
            $table->string('destination');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['destination', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};