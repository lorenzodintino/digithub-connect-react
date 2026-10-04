<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 30);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('preparation_minutes')->default(0);
            $table->unsignedSmallInteger('cleanup_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('resource_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['resource_id', 'starts_at', 'ends_at']);
        });

        Schema::create('service_resource_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['service_id', 'position']);
        });

        Schema::create('resource_service_requirement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_resource_requirement_id')->constrained()->cascadeOnDelete();
            $table->unique(['service_resource_requirement_id', 'resource_id'], 'requirement_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_service_requirement');
        Schema::dropIfExists('service_resource_requirements');
        Schema::dropIfExists('resource_blocks');
        Schema::dropIfExists('resources');
    }
};
