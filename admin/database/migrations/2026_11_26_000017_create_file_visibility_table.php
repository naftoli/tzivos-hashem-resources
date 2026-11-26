<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files › Visibility settings: on/off switches for the Hachayol (Full, each week, each feature).
        // Kept apart from who-can-see / when, so turning something off never clears those, and
        // turning Full or a feature off keeps the weeks and features under it as they were.
        Schema::create('file_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained('sites')->cascadeOnDelete();
            $table->boolean('full')->default(true);
            $table->json('weeks_off')->nullable();   // Hachayol issue numbers turned off, e.g. ["666"]
            $table->json('features')->nullable();    // {"orders": {"on": true, "weeks": null | ["666", "667"]}}; null weeks = every week that is on
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_visibility');
    }
};
