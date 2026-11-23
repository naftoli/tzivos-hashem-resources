<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row each time a signed-in person opens a page or a file on the public site.
        // Who (email + level), what, and when. No name, address, or device is stored.
        Schema::create('content_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('kind', 16);
            $table->string('page_key')->nullable();
            $table->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->string('label');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kind', 'created_at']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_views');
    }
};
