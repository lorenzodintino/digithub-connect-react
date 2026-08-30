<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services that several people book at the same time.
     *
     * A one-to-one service stays exactly as it was: capacity 1, no session,
     * `bookings.user_id` is the person you are meeting. A group service adds a
     * session — the class itself — which holds the seats and the staff giving
     * it, and every booking is a seat on that session.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->default(1)->after('duration_minutes');
        });

        Schema::create('service_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // UTC, like every other instant in this application.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('blocked_starts_at');
            $table->dateTime('blocked_ends_at');

            // Snapshotted: raising the capacity of a service later must not
            // quietly add seats to a class that already ran.
            $table->unsignedSmallInteger('capacity');

            $table->timestamps();

            // One session per service and start: two classes of the same kind
            // at the same minute would be the same class.
            $table->unique(['service_id', 'starts_at']);
            $table->index(['tenant_id', 'starts_at']);
        });

        Schema::create('service_session_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_session_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('service_session_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_session_id');
        });

        Schema::dropIfExists('service_session_user');
        Schema::dropIfExists('service_sessions');

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
