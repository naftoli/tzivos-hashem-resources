<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Five levels: hq_admin, hq_staff, base (Base commanders), teacher (Teachers), news (News site).
    // Existing HQ users become HQ admin; base and teacher keep their stored values.
    public function up(): void
    {
        DB::table('users')->where('role', 'HQ')->update(['role' => 'hq_admin']);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['hq_admin', 'hq_staff'])->update(['role' => 'HQ']);
        DB::table('users')->where('role', 'news')->update(['role' => 'base']);
    }
};
