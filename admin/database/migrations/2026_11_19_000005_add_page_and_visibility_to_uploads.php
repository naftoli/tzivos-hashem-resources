<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('page_url', 1000)->nullable()->after('mime_type');   // full address pasted / picked
            $table->string('page_key')->nullable()->after('page_url');          // the site route, e.g. "campaign"
            $table->foreignId('page_block_id')->nullable()->after('page_key')->constrained('page_blocks')->nullOnDelete();
            $table->string('destination')->nullable()->change();                // old path-style location (older uploads only)
        });

        Schema::create('upload_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained()->cascadeOnDelete();
            $table->string('role');                         // hq_admin, hq_staff, base, teacher, news
            $table->string('mode');                         // always, start, end, limited
            $table->dateTime('starts_at')->nullable();      // UTC
            $table->dateTime('ends_at')->nullable();        // UTC
            $table->string('start_entered_as')->nullable(); // secular or hebrew
            $table->string('end_entered_as')->nullable();
            $table->string('start_hebrew')->nullable();     // e.g. "15 Kislev 5787" when chosen by Hebrew date
            $table->string('end_hebrew')->nullable();
            $table->timestamps();
            $table->unique(['upload_id', 'role']);
        });

        // Files uploaded before this change were open to everyone: keep them open to every level, always
        $now = now();
        foreach (DB::table('uploads')->pluck('id') as $id) {
            foreach (['hq_admin', 'hq_staff', 'base', 'teacher', 'news'] as $role) {
                DB::table('upload_permissions')->insert(['upload_id' => $id, 'role' => $role, 'mode' => 'always', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_permissions');
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_block_id');
            $table->dropColumn(['page_url', 'page_key']);
        });
    }
};
