<?php
declare(strict_types=1);

require_once LIB_PATH . '/relay.php';

$errors  = [];
$success = '';

if (is_post()) {
    csrf_verify();

    $domain      = trim($_POST['domain'] ?? '');
    $maxLog      = (int)($_POST['max_log_rows'] ?? 10000);
    $logDays     = (int)($_POST['log_retention_days'] ?? 0);
    $maxMedia    = (int)($_POST['media_max_mb'] ?? 10) * 1048576;
    $webhookUrl  = trim($_POST['webhook_url'] ?? '');
    $relayLines  = array_filter(array_map('trim', explode("\n", $_POST['relays'] ?? '')));
    $newPw       = $_POST['admin_password'] ?? '';
    $newPw2      = $_POST['admin_password2'] ?? '';

    if (!empty($newPw)) {
        if (strlen($newPw) < 8)  $errors[] = 'Admin password must be at least 8 characters.';
        if ($newPw !== $newPw2)  $errors[] = 'Passwords do not match.';
    }

    if (empty($domain)) $errors[] = 'Domain is required.';

    if (!empty($webhookUrl) && !preg_match('#^https?://#i', $webhookUrl)) {
        $errors[] = 'Webhook URL must start with http:// or https://.';
    }

    foreach ($relayLines as $rl) {
        if (!preg_match('#^https?://#i', $rl)) {
            $errors[] = 'Relay URL must start with http:// or https://: ' . $rl;
        }
    }

    if (!$errors) {
        db_set_setting('domain', $domain);
        db_set_setting('max_log_rows', (string)$maxLog);
        db_set_setting('log_retention_days', (string)$logDays);
        db_set_setting('media_max_bytes', (string)$maxMedia);
        db_set_setting('webhook_url', $webhookUrl);
        sync_relays($relayLines);
        if (!empty($newPw)) {
            db_set_setting('admin_password_hash', password_hash($newPw, PASSWORD_BCRYPT));
        }
        $success = 'Settings saved.';
    }
}

$settings = [
    'domain'              => db_setting('domain', 'example.com'),
    'max_log_rows'        => db_setting('max_log_rows', '10000'),
    'log_retention_days'  => db_setting('log_retention_days', '0'),
    'media_max_mb'        => (int)(db_setting('media_max_bytes', '10485760')) / 1048576,
    'webhook_url'         => db_setting('webhook_url', ''),
];

$relayUrls    = get_relay_urls();
$relayStatus  = get_relay_status_summary();
$totalBots    = count(get_all_accounts());

require BASE_PATH . '/templates/admin/settings.php';
