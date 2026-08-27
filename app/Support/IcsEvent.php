<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Builds the calendar file for a booking.
 *
 * Written by hand because the format is small and adding a dependency for it
 * would not pay for itself. Two details do the real work:
 *
 * - the **UID** never changes, so a calendar recognises a later file as the
 *   same appointment instead of adding a second one;
 * - the **SEQUENCE** grows every time the booking moves, which is what tells
 *   the calendar the new file supersedes the one it already has.
 *
 * Follows RFC 5545 on the two points that quietly break calendars when got
 * wrong: escaping inside text values, and folding lines at 75 octets.
 */
class IcsEvent
{
    private const CRLF = "\r\n";

    public function __construct(private readonly Booking $booking) {}

    /**
     * The whole calendar file, ready to attach or download.
     */
    public function toString(): string
    {
        $booking = $this->booking;
        $service = $booking->service;
        $tenant = $booking->tenant;
        $cancelled = $booking->status === BookingStatus::Cancelled;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escape(config('app.name')).'//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($cancelled ? 'CANCEL' : 'REQUEST'),
            'BEGIN:VEVENT',
            'UID:'.$booking->uid.'@'.$this->host(),
            // Coerced: a null here would emit an empty SEQUENCE, which some
            // calendars reject outright and others silently misread.
            'SEQUENCE:'.(int) $booking->ics_sequence,
            'DTSTAMP:'.$this->stamp(now()),
            'DTSTART:'.$this->stamp($booking->starts_at),
            'DTEND:'.$this->stamp($booking->ends_at),
            'SUMMARY:'.$this->escape($service->name.' · '.$tenant->name),
            'DESCRIPTION:'.$this->escape($this->description()),
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'),
            'ORGANIZER;CN='.$this->escape($tenant->name).':mailto:'.($tenant->notificationReplyTo() ?? $tenant->mailFromAddress()),
        ];

        $lines[] = 'LOCATION:'.$this->escape($this->location());
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode(self::CRLF, array_map($this->fold(...), $lines)).self::CRLF;
    }

    /**
     * The file name offered when the customer downloads it.
     */
    public function fileName(): string
    {
        return Str::slug($this->booking->service->name).'-'.$this->booking->uid.'.ics';
    }

    /**
     * What the calendar entry says in its body.
     */
    private function description(): string
    {
        $parts = [
            __('With :name', ['name' => $this->booking->operator->name]),
            route('portal.booking', [$this->booking->tenant, $this->booking]),
        ];

        if ($this->booking->service->description) {
            array_unshift($parts, $this->booking->service->description);
        }

        return implode("\n", $parts);
    }

    /**
     * Where the appointment takes place.
     *
     * The location type always says something, even when the service adds no
     * detail of its own.
     */
    private function location(): string
    {
        $service = $this->booking->service;

        return $service->location_value
            ? $service->location_type->label().' · '.$service->location_value
            : $service->location_type->label();
    }

    /**
     * Format an instant the way iCalendar expects it, always in UTC.
     */
    private function stamp(CarbonInterface $moment): string
    {
        return $moment->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    /**
     * The domain the UID is anchored to.
     */
    private function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    /**
     * Escape the characters that would otherwise end a value early.
     *
     * Order matters: backslashes first, or the escapes added afterwards would
     * be escaped again.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\n', '\n', '\n', '\;', '\,'],
            $value,
        );
    }

    /**
     * Fold a line to 75 octets, continuing with a leading space.
     *
     * Counted in bytes rather than characters, and never split inside a
     * multi-byte one: an accented letter cut in half corrupts the file.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = mb_strcut($line, 0, 75);
        $rest = substr($line, strlen($folded));

        while ($rest !== '') {
            // 74 to leave room for the leading space of the continuation.
            $chunk = mb_strcut($rest, 0, 74);
            $folded .= self::CRLF.' '.$chunk;
            $rest = substr($rest, strlen($chunk));
        }

        return $folded;
    }
}
