<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['Director', 'Principal', 'Multiple Positions'] as $name) {
            $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
            $exists = DB::table('mashpia_role_maps')->get()->contains(
                fn ($row) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $row->mashpia_role))) === $key
            );
            if ($exists) {
                continue;
            }
            DB::table('mashpia_role_maps')->insert([
                'mashpia_role' => $name,
                'admin_role' => 'base',
                'can_request_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('mashpia_role_maps')->whereIn('mashpia_role', ['Director', 'Principal', 'Multiple Positions'])->delete();
    }
};