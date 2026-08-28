<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a portal declares its legal documents, and where a booking keeps
     * the proof that the customer accepted them.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('privacy_policy_url')->nullable()->after('website');
            $table->string('terms_url')->nullable()->after('privacy_policy_url');
            $table->string('data_controller')->nullable()->after('terms_url');

            // Null means "keep forever": deleting customer data is a decision
            // each portal takes, never a default we take for them.
            $table->unsignedSmallInteger('data_retention_days')->nullable()->after('data_controller');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('privacy_accepted_at')->nullable()->after('customer_locale');

            // The document that was actually accepted: a portal can change its
            // policy later, and the old consent must still say what it covered.
            $table->string('privacy_accepted_url')->nullable()->after('privacy_accepted_at');

            $table->dateTime('anonymised_at')->nullable()->after('privacy_accepted_url');

            $table->index(['tenant_id', 'anonymised_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['privacy_policy_url', 'terms_url', 'data_controller', 'data_retention_days']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'anonymised_at']);
            $table->dropColumn(['privacy_accepted_at', 'privacy_accepted_url', 'anonymised_at']);
        });
    }
};
