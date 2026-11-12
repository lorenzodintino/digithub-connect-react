<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who pays for a portal, for invoicing.
     *
     * One row per portal and kept apart from `tenants` on purpose: the public
     * contact details of the business are a different thing, and a separate
     * table cannot leak through anything that serialises or exports the portal.
     *
     * Every column is optional here. What a complete profile needs depends on
     * the kind and the country, and is decided by App\Support\BillingData: a
     * portal created before this table existed simply has no row.
     */
    public function up(): void
    {
        Schema::create('tenant_billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('kind', 10)->nullable();

            // The legal name: business name, or first and last name together.
            $table->string('name', 120)->nullable();
            $table->string('first_name', 60)->nullable();
            $table->string('last_name', 60)->nullable();
            $table->string('contact_name', 120)->nullable();

            $table->string('tax_code', 30)->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->string('sdi_code', 7)->nullable();
            $table->string('pec', 254)->nullable();

            $table->string('street', 200)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 40)->nullable();
            $table->string('country', 2)->nullable();

            $table->string('email', 254)->nullable();
            $table->string('billing_email', 254)->nullable();
            $table->string('phone', 40)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_profiles');
    }
};
