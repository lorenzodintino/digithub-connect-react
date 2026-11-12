<?php

namespace App\Support;

use Inertia\Inertia;

/**
 * The toast of a React page, sent as Inertia flash data: shown once, never
 * replayed when the person goes back through the browser history.
 */
class Toast
{
    public static function success(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    public static function warning(string $message): void
    {
        Inertia::flash('toast', ['type' => 'warning', 'message' => $message]);
    }

    public static function error(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }
}
