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
            // The last day already paid for; null means the portal never expires.
            $table->date('paid_until')->nullable()->after('plan_id');
            $table->unsignedTinyInteger('subscription_reminders_sent')->default(0)->after('paid_until');
            $table->dateTime('subscription_suspended_at')->nullable()->after('subscription_reminders_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['paid_until', 'subscription_reminders_sent', 'subscription_suspended_at']);
        });
    }
};
