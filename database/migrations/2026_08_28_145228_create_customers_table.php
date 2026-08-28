<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people a portal serves, gathered from the bookings they place.
     *
     * The customer is matched by email inside a single portal: two portals
     * never share an anagraphic, because they are separate data controllers.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Normalised to lowercase before it is written: it is the key the
            // bookings are matched on.
            $table->string('email');

            $table->string('phone')->nullable();
            $table->string('timezone')->nullable();
            $table->string('locale', 5)->nullable();

            // Written by the portal, never by the customer, and never shown on
            // the public pages.
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
