<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Stream rows to the browser as a CSV file.
     *
     * Semicolons and a UTF-8 BOM on purpose: the people who open these files
     * open them in Excel with an Italian locale, where a comma separated file
     * lands in a single column and accented letters come out broken.
     *
     * The rows are streamed, so an export of a busy year does not build the
     * whole file in memory first.
     *
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('The output stream could not be opened for the export.');
            }

            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $headings, ';');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::defuse(...), $row), ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * Keep a spreadsheet from reading a value as a formula.
     *
     * A customer is free to call themselves `=cmd|...`, and Excel would try to
     * run that the moment somebody opens the export. Prefixing an apostrophe
     * is the accepted way of saying "this is text": the cell reads the same and
     * the spreadsheet stops treating it as code.
     */
    private static function defuse(string|int|float|null $value): string|int|float|null
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return str_starts_with($value, '=')
            || str_starts_with($value, '+')
            || str_starts_with($value, '-')
            || str_starts_with($value, '@')
            || str_starts_with($value, "\t")
            || str_starts_with($value, "\r")
            ? "'".$value
            : $value;
    }

    /**
     * A file name that says what it holds and when it was taken.
     */
    public static function fileName(string $subject, string $portal): string
    {
        return str($portal)->slug()->append('-', str($subject)->slug()->value(), '-', now()->format('Y-m-d'))->append('.csv')->value();
    }
}
