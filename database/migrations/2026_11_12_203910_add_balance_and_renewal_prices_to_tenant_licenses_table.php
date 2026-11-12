<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the manager's own licence card shows besides the verdict: the total
     * due and the total paid (the amount still to pay was already kept), and the
     * price of renewing, the active prices of the plan.
     *
     * Still not the single payments, the periods or the customer record: totals
     * and prices are enough for the card and nothing more is copied.
     */
    public function up(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table) {
            $table->integer('due_cents')->nullable()->after('outstanding_cents');
            $table->integer('paid_cents')->nullable()->after('due_cents');
            $table->json('renewal_prices')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table) {
            $table->dropColumn(['due_cents', 'paid_cents', 'renewal_prices']);
        });
    }
};
