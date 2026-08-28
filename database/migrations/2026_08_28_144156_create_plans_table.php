<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The commercial plans a portal can be put on.
     *
     * Nothing enforces these limits yet: this first step is about deciding and
     * seeing them. The checks come once the shape has settled, so they are not
     * written twice.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('description')->nullable();

            // Indicative, and shown to the administrator only: billing is a
            // separate job and does not read this column yet.
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('currency', 3)->default('EUR');

            // Null on any limit means "no limit on this plan".
            $table->unsignedSmallInteger('max_services')->nullable();
            $table->unsignedSmallInteger('max_operators')->nullable();
            $table->unsignedInteger('max_bookings_per_month')->nullable();
            $table->boolean('allows_custom_smtp')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plans');
    }
};
