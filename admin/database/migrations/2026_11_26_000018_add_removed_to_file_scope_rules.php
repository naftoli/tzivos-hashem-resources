<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files dashboard › Remove: a whole Hachayol, a whole feature, or one feature in one week taken off the site
        // for everyone (can be restored). Page text and files on the server are not deleted.
        Schema::table('file_scope_rules', function (Blueprint $table) {
            $table->boolean('removed')->default(false)->after('scope_key');
        });
    }

    public function down(): void
    {
        Schema::table('file_scope_rules', function (Blueprint $table) {
            $table->dropColumn('removed');
        });
    }
};
