<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much warning the customer must give to call an appointment off.
     *
     * Zero means they can cancel up to the last minute. The portal itself is
     * never bound by this: it is a rule for the customer, not for the desk.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('cancellation_notice_hours')->default(48)->after('max_advance_days');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('cancellation_notice_hours');
        });
    }
};
