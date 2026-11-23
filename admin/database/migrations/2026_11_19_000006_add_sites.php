<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * More than one site in the admin. Every page, part and upload belongs to a site.
 * The Resources site becomes site #1 and everything already here is put under it.
 * Only adds tables, columns and indexes: no existing row's content is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('addresses');                 // web addresses of the live site, one per line (host + path)
            $table->string('site_url')->nullable();   // address the "Open site" picker builds page links from
            $table->string('repo')->nullable();       // where the site's source lives (optional)
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('not_imported'); // not_imported | imported
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('archived_at')->nullable(); // archived sites are hidden from the switcher; nothing is deleted
            $table->timestamps();
            $table->index('name');
            $table->index(['archived_at', 'name']);
        });

        $now = now();
        $resourcesId = DB::table('sites')->insertGetId([
            'name' => 'Resources',
            'slug' => 'resources',
            'addresses' => implode("\n", [
                'tzivoshashem.com/resources/site',
                'tzivoshashem.com/public/resources/site',
                'mashpia.com/resources/site',
                'mashpia.com/public/resources/site',
            ]),
            'site_url' => config('admin.site_url', 'https://tzivoshashem.com/resources/site/'),
            'repo' => null,
            'notes' => null,
            'status' => DB::table('pages')->exists() ? 'imported' : 'not_imported',
            'imported_at' => DB::table('pages')->max('imported_at'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (['pages', 'page_blocks', 'uploads'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('site_id')->nullable()->index();
            });
            DB::table($t)->whereNull('site_id')->update(['site_id' => $resourcesId]);
        }

        // Page names only need to be unique inside one site now
        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique('pages_slug_unique');
            $table->unique(['site_id', 'slug']);
        });
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->index(['site_id', 'block_key']);
        });
    }

    public function down(): void
    {
        Schema::table('page_blocks', fn (Blueprint $t) => $t->dropIndex(['site_id', 'block_key']));
        Schema::table('pages', function (Blueprint $t) {
            $t->dropUnique(['site_id', 'slug']);
            $t->unique('slug');
        });
        foreach (['uploads', 'page_blocks', 'pages'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropIndex(['site_id']);
                $table->dropColumn('site_id');
            });
        }
        Schema::dropIfExists('sites');
    }
};
