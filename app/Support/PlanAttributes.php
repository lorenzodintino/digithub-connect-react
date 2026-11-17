<?php

namespace App\Support;

/**
 * The attributes of a plan as the License Manager names them, and what each one
 * means here.
 *
 * The keys are the ones defined in the License Manager for the software (the
 * Italian ones, not the English of the examples in docs/esempio-catalogo-atrio.md).
 * They are written once, here: everywhere else the application speaks of
 * `max_services` and `resources`, which are the columns and the features of
 * App\Models\Plan.
 *
 * Reading rules (guide, §4.6): an attribute that is missing or unknown is «not
 * included» (a feature is off, a limit is zero); a number of `null` is
 * «unlimited»; anything that is not what its type says is not included either.
 */
final class PlanAttributes
{
    /**
     * Numeric attributes: License Manager key => limit column of the plan.
     *
     * @var array<string, string>
     */
    public const LIMITS = [
        'max_servizi' => 'max_services',
        'max_operatori' => 'max_operators',
        'max_prenotazioni_mese' => 'max_bookings_per_month',
        'max_lista_attesa' => 'max_waiting_list',
        'max_pacchetti' => 'max_packages',
    ];

    /**
     * Yes/no attributes: License Manager key => feature of the plan (see Plan::FEATURES).
     *
     * @var array<string, string>
     */
    public const FEATURES = [
        'is_risorse' => 'resources',
        'is_lista_attesa' => 'waiting_list',
        'is_pacchetti' => 'packages',
        'is_servizi_gruppo' => 'group_services',
        'is_pulsanti_calendario' => 'calendar_buttons',
        'is_embed_link' => 'embed',
        'is_custom_smtp' => 'custom_smtp',
    ];

    /**
     * The limits and the features a plan gets from the attributes of the License Manager.
     *
     * Every column and every feature is always set: what the License Manager does not
     * say is not included, so nothing of an older answer is left over.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{max_services: int|null, max_operators: int|null, max_bookings_per_month: int|null, max_waiting_list: int|null, max_packages: int|null, features: array<string, bool>}
     */
    public static function toPlan(array $attributes): array
    {
        $values = [];

        foreach (self::LIMITS as $key => $column) {
            $values[$column] = self::limit($attributes, $key);
        }

        $features = [];

        foreach (self::FEATURES as $key => $feature) {
            $features[$feature] = ($attributes[$key] ?? false) === true;
        }

        return [...$values, 'features' => $features];
    }

    /**
     * Only the attributes Atrio knows, as received: what is kept of a licence.
     *
     * @return array<string, bool|int|null>|null null when there is no list of attributes at all
     */
    public static function known(mixed $attributes): ?array
    {
        if (! is_array($attributes)) {
            return null;
        }

        $kept = [];

        foreach ([...array_keys(self::LIMITS), ...array_keys(self::FEATURES)] as $key) {
            if (array_key_exists($key, $attributes) && (is_bool($attributes[$key]) || is_int($attributes[$key]) || $attributes[$key] === null)) {
                $kept[$key] = $attributes[$key];
            }
        }

        return $kept;
    }

    /**
     * A limit: absent or not a whole number is zero, null is unlimited.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function limit(array $attributes, string $key): ?int
    {
        if (! array_key_exists($key, $attributes)) {
            return 0;
        }

        $value = $attributes[$key];

        return match (true) {
            $value === null => null,
            is_int($value) && $value >= 0 => $value,
            default => 0,
        };
    }
}
