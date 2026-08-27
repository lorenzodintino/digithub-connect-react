<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Default = 'default';

    /**
     * Human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrator'),
            self::Manager => __('Manager'),
            self::Default => __('Operator'),
        };
    }

    /**
     * Flux badge color used when rendering the role.
     */
    public function color(): string
    {
        return match ($this) {
            self::Admin => 'rose',
            self::Manager => 'indigo',
            self::Default => 'zinc',
        };
    }

    /**
     * Determine whether the role operates outside of any portal.
     */
    public function isPlatformRole(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Roles a manager is allowed to create inside their own portal.
     *
     * @return array<int, self>
     */
    public static function assignableByManager(): array
    {
        return [self::Default];
    }
}
