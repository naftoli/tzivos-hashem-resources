<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File type for uploads (from the brief: spread, feature, series, artwork,
 * Hachayol Issue, Hachayol Magazine). Existing uploads get no type ("Not set").
 * Only adds a column and an index: no existing row is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('file_type', 30)->nullable();
            $table->index(['site_id', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'file_type']);
            $table->dropColumn('file_type');
        });
    }
};
