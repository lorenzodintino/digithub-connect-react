<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whole days the portal is shut, for everybody at once.
     *
     * Availability exceptions belong to a single person; holidays and public
     * closures belong to the business, and used to mean walking through the
     * staff one by one.
     */
    public function up(): void
    {
        Schema::create('portal_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Dates, not instants: a closure is read in the portal timezone and
            // covers both ends of the range.
            $table->date('starts_on');
            $table->date('ends_on');

            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_closures');
    }
};
