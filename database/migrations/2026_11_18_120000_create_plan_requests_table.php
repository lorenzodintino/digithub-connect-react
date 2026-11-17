<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The requests of a manager for a bigger plan, with where each one stands
     * (new, taken in charge, handled, rejected, withdrawn).
     *
     * The names of the plans are kept as written at the time: the plan can later
     * be renamed or leave the catalogue, the request still says what was asked.
     */
    public function up(): void
    {
        Schema::create('plan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_plan', 80)->nullable();
            $table->foreignId('requested_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('requested_plan', 80);
            $table->string('reason', 20)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('new');
            $table->text('response')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_requests');
    }
};
