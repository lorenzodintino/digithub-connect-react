<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attach every booking to a customer, building the anagraphic from history.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Null on purpose for the anonymised ones: they have no customer
            // left to point at.
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }

    /**
     * Turn the bookings already taken into customers.
     *
     * The most recent booking wins on name and contacts: it is the freshest
     * thing the customer told the portal about themselves.
     */
    private function backfill(): void
    {
        $bookings = DB::table('bookings')
            ->whereNull('anonymised_at')
            ->where('customer_email', '<>', '')
            ->orderBy('starts_at')
            ->get(['id', 'tenant_id', 'customer_name', 'customer_email', 'customer_phone', 'customer_timezone', 'customer_locale']);

        $customers = [];

        foreach ($bookings as $booking) {
            $email = mb_strtolower(trim($booking->customer_email));
            $key = $booking->tenant_id.'|'.$email;

            if (! isset($customers[$key])) {
                $customers[$key] = DB::table('customers')->insertGetId([
                    'tenant_id' => $booking->tenant_id,
                    'name' => $booking->customer_name,
                    'email' => $email,
                    'phone' => $booking->customer_phone,
                    'timezone' => $booking->customer_timezone,
                    'locale' => $booking->customer_locale,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('customers')->where('id', $customers[$key])->update([
                    'name' => $booking->customer_name,
                    'phone' => $booking->customer_phone,
                    'updated_at' => now(),
                ]);
            }

            DB::table('bookings')->where('id', $booking->id)->update(['customer_id' => $customers[$key]]);
        }
    }
};
