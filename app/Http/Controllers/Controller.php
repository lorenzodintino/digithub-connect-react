<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * A filter read from the query string, as the page wrote it.
     *
     * Anything that is not a plain string — `?q[]=x`, a missing key — falls
     * back to the default, so a hand-made URL never breaks the query.
     */
    protected function filter(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * A `Y-m-d` date from the query string, or an empty string.
     */
    protected function dateFilter(Request $request, string $key): string
    {
        $value = $this->filter($request, $key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? $value
            : '';
    }
}
