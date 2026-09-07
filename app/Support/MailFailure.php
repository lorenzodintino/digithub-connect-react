<?php

namespace App\Support;

use Illuminate\Support\Str;

class MailFailure
{
    /**
     * Turn what the SMTP server said into what the person has to do about it.
     *
     * Transport errors are written for the library, not for the manager: they
     * name response codes, quote the conversation and stay in English inside an
     * Italian screen. The raw text is kept — it is the evidence, and a provider
     * support desk will ask for it — but on its own it does not say which of
     * the six fields above is wrong.
     *
     * Returns null for anything not recognised, so the caller falls back to the
     * server's own words rather than inventing an explanation.
     */
    public static function explain(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $error = Str::lower($error);

        if (Str::contains($error, ['failed to authenticate', 'authentication failed', 'authentication credentials', '535'])) {
            return __('The server rejected the username and password. The username is usually the full email address, not just the part before the @. If the provider uses two-factor authentication, generate an app password for this portal instead of the account one.');
        }

        if (Str::contains($error, 'sender') && Str::contains($error, ['550', '553', 'not allowed', 'not permitted', 'rejected', 'denied'])) {
            return __('The server refused the sender address. Almost every provider only lets you send as the account you sign in with: put that address in the sender field, or leave the field empty.');
        }

        if (Str::contains($error, ['recipient', 'relay']) && Str::contains($error, ['550', '553', '554', 'not allowed', 'denied', 'rejected'])) {
            return __('The server refused the recipient. It may only accept messages for its own domain, or the account may not be allowed to send outside it.');
        }

        if (Str::contains($error, ['getaddrinfo', 'name or service not known', 'host unknown', 'no such host', 'nome sconosciuto', 'host sconosciuto'])) {
            return __('That server name does not exist. Check it for typos: it is the outgoing server of your provider, something like smtp.example.com.');
        }

        if (Str::contains($error, ['ssl', 'tls', 'certificate'])) {
            return __('The encrypted connection failed. Port 465 wants SSL and port 587 wants STARTTLS: check that the port and the encryption go together.');
        }

        if (Str::contains($error, ['connection could not be established', 'connection refused', 'connection timed out', 'timed out', 'stream_socket_client'])) {
            return __('Nothing answered on that server and port. Check the port — usually 587 with STARTTLS, or 465 with SSL — and that your provider allows connections from outside its own network.');
        }

        return null;
    }
}
