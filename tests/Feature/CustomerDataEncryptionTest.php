<?php

use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The free text written about a customer, one row per column that holds it.
 *
 * @return array{string, string, int}
 */
function storedFreeText(string $column): array
{
    $text = 'Allergia al lattice';

    return match ($column) {
        'bookings.customer_notes' => ['bookings', 'customer_notes', Booking::factory()->create(['customer_notes' => $text])->id],
        'customers.notes' => ['customers', 'notes', Customer::factory()->create(['notes' => $text])->id],
        'booking_answers.value' => ['booking_answers', 'value', Booking::factory()->create()->answers()->create(['label' => 'Allergie', 'value' => $text, 'position' => 0])->id],
    };
}

test('free text about a customer is encrypted in the database and readable in the app', function (string $column) {
    [$table, $field, $id] = storedFreeText($column);

    $stored = DB::table($table)->where('id', $id)->value($field);

    expect($stored)->not->toContain('lattice');
    expect(Crypt::decryptString($stored))->toBe('Allergia al lattice');
})->with(['bookings.customer_notes', 'customers.notes', 'booking_answers.value']);

test('the migration encrypts free text written before the cast and leaves encrypted rows alone', function (string $column) {
    [$table, $field, $id] = storedFreeText($column);
    $alreadyEncrypted = DB::table($table)->where('id', $id)->value($field);
    $plainId = storedFreeText($column)[2];
    DB::table($table)->where('id', $plainId)->update([$field => 'Scritta prima della cifratura']);
    $migration = require database_path('migrations/2026_10_04_175454_encrypt_customer_free_text.php');

    $migration->up();

    expect(Crypt::decryptString(DB::table($table)->where('id', $plainId)->value($field)))->toBe('Scritta prima della cifratura');
    expect(DB::table($table)->where('id', $id)->value($field))->toBe($alreadyEncrypted);

    $migration->down();

    expect(DB::table($table)->where('id', $plainId)->value($field))->toBe('Scritta prima della cifratura');
})->with(['bookings.customer_notes', 'customers.notes', 'booking_answers.value']);
