<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the customer answered, kept as written.
     *
     * The label is copied here on purpose: rewording or deleting a question
     * must never change what an old booking says it asked.
     */
    public function up(): void
    {
        Schema::create('booking_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_question_id')->nullable()->constrained()->nullOnDelete();

            $table->string('label', 160);
            $table->text('value');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['booking_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_answers');
    }
};
