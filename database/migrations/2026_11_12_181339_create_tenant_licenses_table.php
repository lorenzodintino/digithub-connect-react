<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The last answer DigitHub License Manager gave about a portal.
     *
     * Only what the platform needs to decide and to show: the verdict, the
     * dates, the plan name and the amount still to be collected (administrator
     * only). The customer record and the payments that come in the answer are
     * deliberately not kept: the portal has no use for them and they would be
     * personal data copied for nothing. The table is the platform's, like the
     * billing profile, and stays out of the portal export.
     *
     * `outcome` is the last valid answer (a 404 is a valid answer too); a null
     * outcome means the License Manager has never answered for this portal.
     * `last_error` is the code of the last attempt that failed, kept so the
     * copy can be served as stale and the administrator can see why.
     */
    public function up(): void
    {
        Schema::create('tenant_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('outcome', 20)->nullable();
            $table->boolean('allowed')->nullable();
            $table->string('reason', 30)->nullable();
            $table->string('state', 20)->nullable();
            $table->string('not_activated_reason', 30)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('lifetime')->default(false);
            $table->unsignedSmallInteger('grace_days')->nullable();

            $table->string('plan_name', 80)->nullable();
            $table->integer('outstanding_cents')->nullable();
            $table->string('currency', 3)->nullable();

            $table->timestamp('checked_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error', 40)->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            // Portals the license does not apply to: demo and test portals that
            // are not registered in the License Manager. They keep the local
            // paid period and plan as before.
            $table->boolean('license_exempt')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('license_exempt');
        });

        Schema::dropIfExists('tenant_licenses');
    }
};
