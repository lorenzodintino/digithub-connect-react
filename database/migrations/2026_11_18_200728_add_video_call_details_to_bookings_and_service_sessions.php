<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['bookings', 'service_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('location_type')->nullable();
                $table->text('meeting_url')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['bookings', 'service_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['location_type', 'meeting_url']);
            });
        }
    }
};
