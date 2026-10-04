<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypt the free text customers and portals write about a customer.
 *
 * The models read these columns through the `encrypted` cast from now on, so
 * the rows written before it have to be encrypted in place. Values that
 * already decrypt are left alone, which makes a second run harmless.
 */
return new class extends Migration
{
    /**
     * The free-text columns, keyed by table.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'bookings' => 'customer_notes',
        'customers' => 'notes',
        'booking_answers' => 'value',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            $this->rewrite($table, $column, fn (string $value): ?string => $this->isEncrypted($value) ? null : Crypt::encryptString($value));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            $this->rewrite($table, $column, fn (string $value): ?string => $this->isEncrypted($value) ? Crypt::decryptString($value) : null);
        }
    }

    /**
     * Rewrite every non-null value of a column, skipping those the callback returns null for.
     *
     * @param  Closure(string): ?string  $transform
     */
    private function rewrite(string $table, string $column, Closure $transform): void
    {
        DB::table($table)->whereNotNull($column)->select(['id', $column])->chunkById(200, function ($rows) use ($table, $column, $transform): void {
            foreach ($rows as $row) {
                $value = $transform($row->{$column});

                if ($value !== null) {
                    DB::table($table)->where('id', $row->id)->update([$column => $value]);
                }
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
