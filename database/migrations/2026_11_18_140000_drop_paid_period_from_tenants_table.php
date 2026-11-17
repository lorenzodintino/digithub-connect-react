<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The paid period was recorded by hand and checked by a command. It is the License Manager now
     * that knows dates, payments and tolerance, so the three columns of that flow go.
     * A portal that was suspended for non-payment stays switched off (`is_active`): the
     * administrator brings it back from the portal page.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['paid_until', 'subscription_reminders_sent', 'subscription_suspended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->date('paid_until')->nullable();
            $table->unsignedTinyInteger('subscription_reminders_sent')->default(0);
            $table->dateTime('subscription_suspended_at')->nullable();
        });
    }
};
