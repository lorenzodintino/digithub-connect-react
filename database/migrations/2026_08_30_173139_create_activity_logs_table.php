<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what, and when.
     *
     * The actor is kept twice on purpose: the foreign key for filtering, and a
     * copy of the name and role so a deleted account does not turn its history
     * into a list of blanks.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Null for the actions of the platform itself, which belong to no portal.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('actor_name');
            $table->string('actor_role', 20)->nullable();

            // A dotted name, like `booking.cancelled`: the part before the dot
            // is what it happened to, the part after is what happened.
            $table->string('action', 60);

            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Something a human can read months later, without opening the row
            // it points at. Deliberately short and never a customer name.
            $table->string('subject_label')->nullable();

            // References and numbers, not copies of personal data.
            $table->json('context')->nullable();

            $table->string('ip_address', 45)->nullable();

            // Written once and never touched again: no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
