<?php
declare(strict_types=1);

function base_url(): string {
    $domain = db_setting('domain', 'example.com');
    $domain = rtrim($domain, '/');
    $scheme = in_array($domain, ['localhost', '127.0.0.1', '::1'], true)
           || str_starts_with($domain, 'localhost:')
        ? 'http'
        : 'https';
    return $scheme . '://' . $domain;
}

function actor_url(string $username): string {
    return base_url() . '/users/' . $username;
}

function profile_url(string $username): string {
    return base_url() . '/@' . $username;
}

function inbox_url(string $username): string {
    return actor_url($username) . '/inbox';
}

function outbox_url(string $username): string {
    return actor_url($username) . '/outbox';
}

function followers_url(string $username): string {
    return actor_url($username) . '/followers';
}

function following_url(string $username): string {
    return actor_url($username) . '/following';
}

function featured_tags_url(string $username): string {
    return actor_url($username) . '/featured_tags';
}

function generate_uuid(): string {
    $bytes    = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function new_activity_id(string $username): string {
    return actor_url($username) . '/statuses/' . generate_uuid();
}

function ap_timestamp(?string $datetime = null): string {
    $ts = $datetime ? strtotime($datetime) : time();
    if ($ts === false) $ts = time();
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

function wants_activitypub(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (
        str_contains($accept, 'application/activity+json') ||
        str_contains($accept, 'application/ld+json')
    );
}

function json_response(array $data, int $code = 200, string $contentType = 'application/activity+json'): never {
    http_response_code($code);
    header('Content-Type: ' . $contentType . '; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error_response(int $code, string $message): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function request_body(): string {
    static $body = null;
    if ($body !== null) return $body;
    $raw = (string)file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024 + 1);
    if (strlen($raw) > 2 * 1024 * 1024) {
        error_response(413, 'Payload too large');
    }
    $body = $raw;
    return $body;
}

function request_json(): array {
    $body    = request_body();
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function sanitize_username(string $u): string {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $u) ?? '';
}

function visibility_to_addressees(string $visibility, string $username, array $mentionedUris = []): array {
    $public    = 'https://www.w3.org/ns/activitystreams#Public';
    $followers = followers_url($username);

    return match ($visibility) {
        'public'   => ['to' => [$public],     'cc' => array_merge([$followers], $mentionedUris)],
        'unlisted' => ['to' => [$followers],  'cc' => array_merge([$public],    $mentionedUris)],
        'private'  => ['to' => [$followers],  'cc' => $mentionedUris],
        'direct'   => ['to' => $mentionedUris,'cc' => []],
        default    => ['to' => [$public],     'cc' => [$followers]],
    };
}

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token    = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($expected, $token)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function is_post(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function get_domain(): string {
    return db_setting('domain', 'example.com') ?? 'example.com';
}

function is_local(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = strtolower(explode(':', $host)[0]);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function site_url(string $path = ''): string {
    if (is_local()) {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : ($base !== '' ? '' : '/'));
    }
    $domain = db_setting('domain', '') ?: '';
    if ($domain === '' || $domain === 'example.com') {
        // Domain not configured yet — use relative URLs so setup page works
        return ($path !== '' ? '/' . ltrim($path, '/') : '/');
    }
    return base_url() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function fire_webhook(string $event, array $payload): void {
    $url = db_setting('webhook_url', '');
    if (empty($url)) return;

    $payload['event']     = $event;
    $payload['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
