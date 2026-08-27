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
        Schema::table('tenants', function (Blueprint $table) {
            // How long before an appointment the customer is reminded.
            // Null switches reminders off for the portal.
            $table->unsignedSmallInteger('reminder_hours_before')->nullable()->default(24)->after('notify_manager_on_booking');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // The only defence against sending the same reminder twice.
            $table->dateTime('reminder_sent_at')->nullable()->after('rescheduled_at');

            $table->index(['reminder_sent_at', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('reminder_hours_before');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['reminder_sent_at', 'starts_at']);
            $table->dropColumn('reminder_sent_at');
        });
    }
};
