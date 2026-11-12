<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The identifier a portal has for DigitHub License Manager (its `externalId`).
     *
     * An UUID and not the numeric `id`: the numeric one depends on the database
     * and starts again from 1 when the database is recreated or restored from
     * an old backup, which would hand a new portal the licence of an old one.
     * The UUID does not depend on the database, so it cannot repeat across
     * environments either. It is born with the portal and never changes: once
     * the License Manager knows it, it is the portal. The unique index is the
     * real guarantee of uniqueness.
     *
     * The column stays nullable at the database level (SQLite cannot make it
     * strict without rebuilding the table that every other table points to);
     * Tenant::creating always fills it.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('license_id', 36)->nullable()->unique()->after('slug');
        });

        DB::table('tenants')->whereNull('license_id')->orderBy('id')->each(function (object $tenant): void {
            DB::table('tenants')->where('id', $tenant->id)->update(['license_id' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['license_id']);
            $table->dropColumn('license_id');
        });
    }
};
