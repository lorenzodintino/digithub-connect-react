<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the licence of a portal says about its plan: the stable `code` (the plan is
     * recognised by it, never by the name) and the attributes the License Manager sent
     * with it, so limits and features come from the last answer even when the catalogue
     * cannot be read. A copy from before this has neither: it is asked again at the next
     * request (see LicenseService::needsLiveCheck).
     */
    public function up(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table) {
            $table->string('plan_code', 40)->nullable()->after('plan_name');
            $table->json('plan_attributes')->nullable()->after('plan_code');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table) {
            $table->dropColumn(['plan_code', 'plan_attributes']);
        });
    }
};
