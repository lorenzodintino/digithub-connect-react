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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 40)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->text('customer_notes')->nullable();
            $table->string('customer_timezone');

            // Always stored in UTC. The blocked window adds the service buffers and is
            // snapshotted so later buffer changes never move an existing booking.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('blocked_starts_at');
            $table->dateTime('blocked_ends_at');

            $table->string('status')->default('confirmed');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            // A reschedule moves the booking in place so the customer link keeps
            // working; these two columns remember that it happened.
            $table->dateTime('rescheduled_at')->nullable();
            $table->dateTime('original_starts_at')->nullable();

            $table->unsignedInteger('price_cents')->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->timestamps();

            $table->index(['user_id', 'blocked_starts_at', 'blocked_ends_at']);
            $table->index(['tenant_id', 'starts_at']);
            $table->index(['service_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
