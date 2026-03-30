<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/http_sig.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/activity.php';

/**
 * Deliver an activity to all accepted followers of a local account.
 * Also accepts extra inbox URLs (e.g. for DMs or explicit targets).
 */
function deliver_to_followers(array $activity, array $account, array $extraInboxes = []): void
{
    $followers = db_all(
        "SELECT follower_inbox, shared_inbox FROM followers
         WHERE account_id = ? AND accepted = 1",
        [$account['id']]
    );

    // Deduplicate inboxes (prefer shared inbox)
    $inboxes = [];
    foreach ($followers as $f) {
        $inbox = !empty($f['shared_inbox']) ? $f['shared_inbox'] : $f['follower_inbox'];
        if (!empty($inbox))
            $inboxes[$inbox] = true;
    }
    foreach ($extraInboxes as $inbox) {
        if (!empty($inbox))
            $inboxes[$inbox] = true;
    }

    // Include active relay inboxes for public activities
    $to = (array)($activity['to'] ?? []);
    $cc = (array)($activity['cc'] ?? []);
    if (in_array(AP_PUBLIC, array_merge($to, $cc), true)) {
        require_once __DIR__ . '/relay.php';
        foreach (get_relay_inboxes() as $relayInbox) {
            $inboxes[$relayInbox] = true;
        }
    }

    $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false)
        return;

    foreach (array_keys($inboxes) as $inboxUrl) {
        deliver_to_inbox($inboxUrl, $body, $account, $activity['type'] ?? 'Activity');
    }
}

/**
 * Deliver an activity JSON body to a single inbox URL, signing with HTTP Signatures.
 */
function deliver_to_inbox(string $inboxUrl, string $body, array $account, string $activityType): void
{
    $logId = log_activity(
        (int) $account['id'],
        'out',
        $activityType,
        $body,
        '',
        $inboxUrl,
        'pending'
    );

    try {
        $headers = http_sig_sign($inboxUrl, $body, $account);
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init($inboxUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'ActivityPub-Bot/1.0 (+' . base_url() . ')',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            throw new RuntimeException('cURL error: ' . $curlError);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            update_log_status($logId, 'delivered');
        } else {
            update_log_status($logId, 'failed', "HTTP {$httpCode}: " . substr((string) $response, 0, 300));
        }
    } catch (Throwable $e) {
        update_log_status($logId, 'failed', $e->getMessage());
    }
}

/**
 * Resolve a remote actor's inbox URLs.
 */
function resolve_actor_inbox(string $actorUri): array
{
    $actor = fetch_remote_actor($actorUri);
    if ($actor === null)
        return ['inbox' => '', 'shared_inbox' => ''];

    return [
        'inbox' => $actor['inbox'] ?? '',
        'shared_inbox' => $actor['endpoints']['sharedInbox'] ?? '',
    ];
}

/**
 * Deliver an activity to a single remote actor (by resolving their inbox).
 */
function deliver_to_actor(array $activity, array $account, string $targetActorUri): void
{
    $inboxInfo = resolve_actor_inbox($targetActorUri);
    $inbox = $inboxInfo['shared_inbox'] ?: $inboxInfo['inbox'];
    if (empty($inbox))
        return;

    $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false)
        return;

    deliver_to_inbox($inbox, $body, $account, $activity['type'] ?? 'Activity');
}
