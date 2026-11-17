<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The month (Y-m, in the portal's timezone) for which the administrators were
     * already told that the portal took all the bookings of its plan, so the
     * message goes out once a month and not at every booking past the limit.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('bookings_limit_notified_month', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('bookings_limit_notified_month');
        });
    }
};
