<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A file that is part of a page's own HTML (a picture or a file link): removed from the site, or limited to some levels.
        Schema::create('page_file_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_block_id')->constrained('page_blocks')->cascadeOnDelete();
            $table->string('ref', 1000);              // "img:<picture id>" or "link:<href as written in the page>"
            $table->boolean('removed')->default(false); // removed from the site (can be restored)
            $table->json('roles')->nullable();         // levels that can see it; null = everyone
            $table->json('download_roles')->nullable(); // levels that get "Download" (Hachayol PDF bar); null = everyone who can see it
            $table->timestamp('starts_at')->nullable();   // "when": shown from
            $table->timestamp('ends_at')->nullable();     // "when": shown until
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('page_block_id');
        });

        // Files dashboard: who can see / download, and when, for a whole Hachayol issue, a whole feature, or one feature in one week.
        // Only changes what the site shows; files stay on the server.
        Schema::create('file_scope_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('scope', 16);               // issue (e.g. 666), feature (e.g. orders), part (e.g. i666orders)
            $table->string('scope_key', 190);
            $table->json('roles')->nullable();          // null = everyone
            $table->json('download_roles')->nullable(); // issues only: who gets Download on the Hachayol bar; null = everyone who can see
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'scope', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_scope_rules');
        Schema::dropIfExists('page_file_rules');
    }
};
