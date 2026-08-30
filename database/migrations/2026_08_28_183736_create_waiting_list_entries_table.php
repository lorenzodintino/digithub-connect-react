<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People waiting for a place that does not exist yet.
     *
     * One entry per service and email: asking twice means changing your mind
     * about the dates, not queueing twice.
     */
    public function up(): void
    {
        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->id();

            // Unguessable, like a booking: it is the key of the link that lets
            // somebody take themselves off the list without an account.
            $table->string('uid', 40)->unique();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('customer_timezone');
            $table->string('customer_locale', 5);

            // Dates, read in the portal timezone, both ends included.
            $table->date('wanted_from');
            $table->date('wanted_to');

            $table->dateTime('privacy_accepted_at')->nullable();
            $table->string('privacy_accepted_url')->nullable();

            // When we last told this person that something opened up: it keeps
            // a busy day from turning into a mailbox full of the same message.
            $table->dateTime('notified_at')->nullable();

            $table->timestamps();

            $table->unique(['service_id', 'customer_email']);
            $table->index(['tenant_id', 'wanted_from', 'wanted_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_list_entries');
    }
};
