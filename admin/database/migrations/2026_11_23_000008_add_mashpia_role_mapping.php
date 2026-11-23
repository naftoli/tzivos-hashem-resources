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
            $table->string('mashpia_role')->nullable()->after('role');
            $table->boolean('role_locked')->default(false)->after('mashpia_role');
        });

        Schema::create('mashpia_role_maps', function (Blueprint $table) {
            $table->id();
            $table->string('mashpia_role')->unique();
            $table->string('admin_role');
            $table->boolean('can_request_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('admin_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $requestRoles = [];
        foreach (config('mashpia.request_admin_roles', []) as $name) {
            $requestRoles[] = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
        }
        foreach (config('mashpia.role_map', []) as $mashpia => $admin) {
            $admin = (string) $admin;
            if ($admin === 'hq_admin') {
                $admin = 'hq_staff';
            }
            $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $mashpia)));
            DB::table('mashpia_role_maps')->insert([
                'mashpia_role' => $mashpia,
                'admin_role' => $admin,
                'can_request_admin' => in_array($key, $requestRoles, true),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_access_requests');
        Schema::dropIfExists('mashpia_role_maps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mashpia_role', 'role_locked']);
        });
    }
};
