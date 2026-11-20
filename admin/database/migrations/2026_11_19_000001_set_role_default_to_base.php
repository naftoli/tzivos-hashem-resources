<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('base')->change();
        });
        DB::table('users')->where('role', 'Teacher')->update(['role' => 'teacher']);
        DB::table('users')->whereNotIn('role', ['HQ', 'teacher', 'base'])->update(['role' => 'base']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('Teacher')->change();
        });
    }
};