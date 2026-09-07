<?php

/**
 * Guida l'applicazione via HTTP per il collaudo manuale, come farebbe un browser:
 * sessione con cookie, token CSRF letto dal cookie XSRF-TOKEN, e chiamate vere
 * all'endpoint di Livewire con lo snapshot preso dalla pagina renderizzata.
 *
 * NON è una scorciatoia per i test: quelli restano in tests/, sono più
 * importanti e coprono il comportamento. Questo serve alla procedura «Da
 * collaudare a mano» del LOG, per percorrerne i passi quando non c'è nessuno
 * davanti alla tastiera — e non sa dire se una schermata si capisce, che è
 * esattamente la ragione per cui il Giro 5 esiste.
 *
 * Uso, con `php artisan serve --port=8123` acceso:
 *
 *   require 'collaudo-driver.php';
 *   $d = new Driver(__DIR__.'/admin.cookies');
 *   $d->login('admin@digithub.test', 'password');
 *   $d->get('/admin/portals/collaudo-giro-4b');
 *   $c = $d->component('smtp-settings');
 *   $r = $d->livewire($c, [['sendTest', []]], ['test_recipient' => 'admin@digithub.test']);
 */
class Driver
{
    public string $base = 'http://127.0.0.1:8123';

    public string $body = '';

    public int $status = 0;

    public string $headers = '';

    /** Il prefisso dell'endpoint Livewire è offuscato: si legge dalla pagina. */
    public string $updateUri = '/livewire/update';

    public function __construct(public string $jar) {}

    /** @return array{status:int,body:string,headers:string} */
    private function curl(string $method, string $url, ?string $payload = null, array $extraHeaders = []): array
    {
        $ch = curl_init();
        $headers = $extraHeaders;

        curl_setopt_array($ch, [
            CURLOPT_URL => str_starts_with($url, 'http') ? $url : $this->base.$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // curl_close non serve da PHP 8.0

        $this->headers = substr($raw, 0, $headerSize);
        $this->body = substr($raw, $headerSize);
        $this->status = $status;

        if (preg_match('#["\']((?:https?://[^"\']*?)?/livewire[a-z0-9\-]*/update)["\']#', $this->body, $m)) {
            $this->updateUri = $m[1];
        }

        return ['status' => $status, 'body' => $this->body, 'headers' => $this->headers];
    }

    public function get(string $url): string
    {
        $this->curl('GET', $url);

        while (in_array($this->status, [301, 302, 303, 307, 308], true)) {
            preg_match('/^location:\s*(\S+)/mi', $this->headers, $m);
            $this->curl('GET', trim($m[1]));
        }

        return $this->body;
    }

    /** Segue un solo passo, per poter guardare il redirect. */
    public function getRaw(string $url): string
    {
        $this->curl('GET', $url);

        return $this->body;
    }

    public function token(): string
    {
        preg_match('/name="_token"\s+value="([^"]+)"/', $this->body, $m);

        return $m[1] ?? $this->tokenFromMeta();
    }

    private function tokenFromMeta(): string
    {
        preg_match('/name="csrf-token"\s+content="([^"]+)"/', $this->body, $m);

        return $m[1] ?? '';
    }

    /**
     * Il token CSRF cifrato che Laravel mette nel cookie, quello che il
     * browser rimanda a Livewire nell'intestazione X-XSRF-TOKEN.
     */
    public function xsrf(): string
    {
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $parts = preg_split('/\t/', $line);

            if (($parts[5] ?? '') === 'XSRF-TOKEN') {
                return urldecode($parts[6] ?? '');
            }
        }

        return '';
    }

    public function login(string $email, string $password): bool
    {
        $this->get('/login');
        $token = $this->token();
        $this->curl('POST', '/login', http_build_query([
            '_token' => $token,
            'email' => $email,
            'password' => $password,
        ]));

        return $this->status === 302 && ! str_contains($this->headers, '/login');
    }

    public function logout(): void
    {
        $this->get('/dashboard');
        $token = $this->tokenFromMeta();
        $this->curl('POST', '/logout', http_build_query(['_token' => $token]));
    }

    /**
     * Estrae gli snapshot dei componenti Livewire presenti nella pagina.
     *
     * @return array<int, array{id:string,name:string,snapshot:array}>
     */
    public function components(): array
    {
        preg_match_all('/wire:snapshot="([^"]*)"/', $this->body, $m);
        $out = [];

        foreach ($m[1] as $encoded) {
            $json = html_entity_decode($encoded, ENT_QUOTES, 'UTF-8');
            $snapshot = json_decode($json, true);

            if (! is_array($snapshot)) {
                continue;
            }

            $out[] = [
                'id' => $snapshot['memo']['id'] ?? '',
                'name' => $snapshot['memo']['name'] ?? '',
                'snapshot' => $snapshot,
            ];
        }

        return $out;
    }

    /** Trova il componente il cui nome contiene $needle. */
    public function component(string $needle = ''): array
    {
        foreach ($this->components() as $c) {
            if ($needle === '' || str_contains($c['name'], $needle)) {
                return $c;
            }
        }

        throw new RuntimeException("Componente [$needle] non trovato. Presenti: ".
            implode(', ', array_column($this->components(), 'name')));
    }

    /**
     * Chiama un metodo su un componente Livewire e restituisce la risposta decodificata.
     *
     * @param  array<int, array{0:string,1:array}>  $calls  coppie [metodo, parametri]
     * @param  array<string, mixed>  $updates
     */
    public function livewire(array $component, array $calls = [], array $updates = []): array
    {
        $payload = [
            'components' => [[
                'snapshot' => json_encode($component['snapshot']),
                'updates' => (object) $updates,
                'calls' => array_map(fn ($c) => [
                    'path' => '',
                    'method' => $c[0],
                    'params' => $c[1] ?? [],
                ], $calls),
            ]],
        ];

        $this->curl('POST', $this->updateUri, json_encode($payload), [
            'Content-Type: application/json',
            'X-Livewire: 1',
            'X-XSRF-TOKEN: '.$this->xsrf(),
            'Referer: '.$this->base,
        ]);

        $decoded = json_decode($this->body, true);

        if (! is_array($decoded)) {
            return ['error' => true, 'status' => $this->status, 'raw' => substr($this->body, 0, 2000)];
        }

        return $decoded;
    }

    /** Aggiorna lo snapshot in mano dopo una chiamata riuscita. */
    public function refresh(array $response): array
    {
        $c = $response['components'][0] ?? null;

        if (! $c) {
            return [];
        }

        return [
            'id' => $c['snapshot']['memo']['id'] ?? '',
            'name' => $c['snapshot']['memo']['name'] ?? '',
            'snapshot' => json_decode($c['snapshot'], true) ?: $c['snapshot'],
        ];
    }

    /** Il testo visibile della pagina, come lo leggerebbe un browser. */
    public function text(?string $html = null): string
    {
        $html = $html ?? $this->body;

        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//script | //style | //template | //svg') as $node) {
            $node->parentNode?->removeChild($node);
        }

        return trim(preg_replace('/\s+/u', ' ', $dom->textContent ?? ''));
    }

    public function seesText(string $needle, ?string $html = null): bool
    {
        return str_contains($this->text($html), $needle);
    }
}

function ok(string $label, bool $pass, string $detail = ''): bool
{
    echo ($pass ? '  OK   ' : '  FAIL ').$label.($detail !== '' ? " — $detail" : '').PHP_EOL;

    return $pass;
}

function step(string $title): void
{
    echo PHP_EOL.'=== '.$title.' ==='.PHP_EOL;
}
