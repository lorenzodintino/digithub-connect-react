<?php

/**
 * Server SMTP finto per il collaudo locale della posta.
 *
 * Accetta una connessione per volta, parla il minimo indispensabile del
 * protocollo e scrive ogni messaggio ricevuto in un file, con il MAIL FROM
 * in chiaro: serve a vedere *da quale mittente* parte davvero ogni email.
 *
 *   php scratchpad/fake-smtp.php 1025 piattaforma
 *   php scratchpad/fake-smtp.php 1026 portale
 *
 * Opzioni:
 *   --reject-sender=dominio   risponde 550 a un MAIL FROM su quel dominio
 *   --reject-auth             risponde 535 all'autenticazione
 */
$port = (int) ($argv[1] ?? 1025);
$label = $argv[2] ?? 'smtp';
$rejectSender = null;
$rejectAuth = false;

foreach (array_slice($argv, 3) as $arg) {
    if (str_starts_with($arg, '--reject-sender=')) {
        $rejectSender = substr($arg, 16);
    }
    if ($arg === '--reject-auth') {
        $rejectAuth = true;
    }
}

$mailbox = __DIR__.'/mailbox-'.$label;
@mkdir($mailbox, 0777, true);

$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);

if (! $server) {
    fwrite(STDERR, "Impossibile ascoltare sulla porta $port: $errstr\n");
    exit(1);
}

echo "[$label] in ascolto su 127.0.0.1:$port — messaggi in $mailbox\n";

if ($rejectSender !== null) {
    echo "[$label] rifiuta i mittenti su @$rejectSender\n";
}

if ($rejectAuth) {
    echo "[$label] rifiuta l'autenticazione\n";
}

while ($conn = @stream_socket_accept($server, -1)) {
    handleConnection($conn, $label, $mailbox, $rejectSender, $rejectAuth);
    fclose($conn);
}

function handleConnection($conn, string $label, string $mailbox, ?string $rejectSender, bool $rejectAuth): void
{
    $say = function (string $line) use ($conn) {
        fwrite($conn, $line."\r\n");
    };

    $say('220 fake-smtp ESMTP pronto');

    $from = '';
    $recipients = [];
    $data = '';
    $inData = false;

    while (($line = fgets($conn)) !== false) {
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                $inData = false;
                $file = $mailbox.'/'.date('Ymd-His').'-'.substr(md5($data.microtime()), 0, 6).'.eml';
                file_put_contents($file, "X-Fake-Mail-From: $from\nX-Fake-Rcpt-To: ".implode(', ', $recipients)."\n".$data);
                echo "[$label] ricevuto da <$from> per ".implode(', ', $recipients).' → '.basename($file)."\n";
                $say('250 OK messaggio accettato');
                $data = '';
                $recipients = [];

                continue;
            }

            $data .= $line;

            continue;
        }

        $command = strtoupper(substr(trim($line), 0, 4));

        switch ($command) {
            case 'EHLO':
            case 'HELO':
                $say('250-fake-smtp');
                $say('250-AUTH LOGIN PLAIN');
                $say('250 SIZE 10485760');
                break;

            case 'AUTH':
                if ($rejectAuth) {
                    $say('535 5.7.8 Authentication credentials invalid');
                    break;
                }

                if (stripos($line, 'AUTH LOGIN') === 0 && strlen(trim($line)) <= 11) {
                    $say('334 VXNlcm5hbWU6');
                    fgets($conn);
                    $say('334 UGFzc3dvcmQ6');
                    fgets($conn);
                }

                $say('235 2.7.0 Authentication successful');
                break;

            case 'MAIL':
                preg_match('/<([^>]*)>/', $line, $m);
                $from = $m[1] ?? '';

                if ($rejectSender !== null && str_ends_with($from, '@'.$rejectSender)) {
                    echo "[$label] RIFIUTATO il mittente <$from>\n";
                    $say('550 5.7.1 Sender address not allowed');
                    break;
                }

                $say('250 OK mittente accettato');
                break;

            case 'RCPT':
                preg_match('/<([^>]*)>/', $line, $m);
                $recipients[] = $m[1] ?? '';
                $say('250 OK destinatario accettato');
                break;

            case 'DATA':
                $inData = true;
                $say('354 Scrivi il messaggio, chiudi con un punto su una riga sola');
                break;

            case 'RSET':
                $from = '';
                $recipients = [];
                $say('250 OK');
                break;

            case 'QUIT':
                $say('221 Arrivederci');

                return;

            default:
                $say('250 OK');
        }
    }
}
