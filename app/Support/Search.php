<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Search
{
    /**
     * Filter the query to rows where any of the columns contains the term.
     *
     * The term is escaped before it reaches `LIKE`, so a customer searching for
     * `100%` or `a_b` gets those literal characters instead of wildcards. `!` is
     * used as the escape character because a backslash is not portable: MySQL
     * would swallow it inside the string literal.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, literal-string>  $columns
     */
    public static function contains(Builder $query, array $columns, string $term): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $query->where(function (Builder $inner) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
