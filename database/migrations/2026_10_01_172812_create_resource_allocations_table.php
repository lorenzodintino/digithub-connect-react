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
        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('resource_name');
            $table->string('requirement_name');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('preparation_minutes');
            $table->unsignedSmallInteger('cleanup_minutes');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->index(['resource_id', 'starts_at', 'ends_at']);
        });

        Schema::create('booking_resource_allocation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_allocation_id')->constrained()->cascadeOnDelete();
            $table->unique(['booking_id', 'resource_allocation_id'], 'booking_resource_allocation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_resource_allocation');
        Schema::dropIfExists('resource_allocations');
    }
};
