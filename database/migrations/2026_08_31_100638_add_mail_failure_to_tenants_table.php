<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real delivery that failed, as opposed to a test that did.
     *
     * Without it a broken SMTP server stays invisible: the bookings keep
     * coming, the emails pile up in failed_jobs, and the only screen that says
     * anything speaks about the last test — which may have passed months ago.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dateTime('mail_failed_at')->nullable()->after('mail_error');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('mail_failed_at');
        });
    }
};
