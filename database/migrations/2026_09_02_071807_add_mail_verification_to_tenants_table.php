<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the portal's own SMTP server last proved that it works.
     *
     * Settings alone no longer divert the mail: until a test message actually
     * goes through, and again as soon as a delivery fails, the portal sends
     * through the platform. A wrong password can lose a From address, never a
     * booking confirmation.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dateTime('mail_verified_at')->nullable()->after('mail_failed_at');
        });

        // Whoever was already sending on their own has a working server: the
        // last test passed and nothing has failed since.
        DB::table('tenants')
            ->whereNotNull('mail_host')
            ->whereNotNull('mail_username')
            ->whereNotNull('mail_password')
            ->whereNotNull('mail_tested_at')
            ->whereNull('mail_error')
            ->update(['mail_verified_at' => DB::raw('mail_tested_at')]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('mail_verified_at');
        });
    }
};
