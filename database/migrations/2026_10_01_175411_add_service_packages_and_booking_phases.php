<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_package')->default(false);
            $table->boolean('use_component_prices')->default(true);
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('pause_after_minutes')->default(0);
            $table->timestamps();
            $table->unique(['package_id', 'position']);
            $table->unique(['package_id', 'service_id']);
        });

        Schema::create('booking_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('pause_after_minutes');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('buffer_before_minutes');
            $table->unsignedSmallInteger('buffer_after_minutes');
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('currency', 3);
            $table->timestamps();
            $table->unique(['booking_id', 'position']);
        });

        Schema::table('resource_allocations', function (Blueprint $table) {
            $table->foreignId('booking_phase_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_allocations', fn (Blueprint $table) => $table->dropConstrainedForeignId('booking_phase_id'));
        Schema::dropIfExists('booking_phases');
        Schema::dropIfExists('service_package_items');
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['is_package', 'use_component_prices']));
    }
};
