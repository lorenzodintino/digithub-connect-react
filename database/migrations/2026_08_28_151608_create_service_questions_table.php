<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The extra questions a service asks when somebody books it.
     */
    public function up(): void
    {
        Schema::create('service_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->string('label', 160);
            $table->string('type', 20);

            // The choices, for the types that are answered by picking one.
            $table->json('options')->nullable();

            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['service_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_questions');
    }
};
