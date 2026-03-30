<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/deliver.php';

/**
 * Get all accepted relay inbox URLs (deduplicated).
 */
function get_relay_inboxes(): array
{
    $rows = db_all("SELECT DISTINCT inbox_url FROM relays WHERE state = 'accepted'");
    return array_column($rows, 'inbox_url');
}

/**
 * Get all distinct relay inbox URLs currently configured.
 */
function get_relay_urls(): array
{
    $rows = db_all("SELECT DISTINCT inbox_url FROM relays ORDER BY inbox_url");
    return array_column($rows, 'inbox_url');
}

/**
 * Get relay status summary per inbox URL.
 * Returns ['url' => ['total' => N, 'accepted' => N, 'pending' => N, 'rejected' => N]]
 */
function get_relay_status_summary(): array
{
    $rows = db_all(
        "SELECT inbox_url, state, COUNT(*) as cnt FROM relays GROUP BY inbox_url, state"
    );
    $summary = [];
    foreach ($rows as $r) {
        $url = $r['inbox_url'];
        if (!isset($summary[$url])) {
            $summary[$url] = ['total' => 0, 'accepted' => 0, 'pending' => 0, 'rejected' => 0];
        }
        $summary[$url][$r['state']] += (int)$r['cnt'];
        $summary[$url]['total'] += (int)$r['cnt'];
    }
    return $summary;
}

/**
 * Sync relay subscriptions: subscribe new URLs, unsubscribe removed URLs.
 * Called when settings are saved with the parsed list of relay inbox URLs.
 */
function sync_relays(array $newUrls): void
{
    $newUrls = array_values(array_unique(array_filter(array_map('trim', $newUrls))));
    $currentUrls = get_relay_urls();
    $accounts = get_all_accounts();

    if (empty($accounts)) return;

    $toAdd    = array_diff($newUrls, $currentUrls);
    $toRemove = array_diff($currentUrls, $newUrls);

    // Subscribe all bots to new relays
    foreach ($toAdd as $url) {
        foreach ($accounts as $account) {
            subscribe_account_to_relay($account, $url);
        }
    }

    // Unsubscribe all bots from removed relays
    foreach ($toRemove as $url) {
        $relays = db_all(
            "SELECT r.*, a.username, a.private_key, a.public_key
             FROM relays r
             JOIN accounts a ON a.id = r.account_id
             WHERE r.inbox_url = ?",
            [$url]
        );
        foreach ($relays as $relay) {
            unsubscribe_account_from_relay($relay, $url);
        }
    }
}

/**
 * Subscribe a single account to a relay.
 */
function subscribe_account_to_relay(array $account, string $inboxUrl): void
{
    $follow = build_relay_follow($account);
    $followId = $follow['id'];

    db_run(
        "INSERT OR IGNORE INTO relays (inbox_url, account_id, state, follow_activity_id)
         VALUES (?, ?, 'pending', ?)",
        [$inboxUrl, $account['id'], $followId]
    );

    $body = json_encode($follow, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body !== false) {
        deliver_to_inbox($inboxUrl, $body, $account, 'Follow');
    }
}

/**
 * Unsubscribe a single account from a relay.
 * $relay must include account fields (username, private_key, public_key) plus relay fields.
 */
function unsubscribe_account_from_relay(array $relay, string $inboxUrl): void
{
    $account = [
        'id'          => $relay['account_id'],
        'username'    => $relay['username'],
        'private_key' => $relay['private_key'],
        'public_key'  => $relay['public_key'],
    ];

    $undo = build_relay_unfollow($account, $relay['follow_activity_id']);
    $body = json_encode($undo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body !== false) {
        deliver_to_inbox($inboxUrl, $body, $account, 'Undo');
    }

    db_run("DELETE FROM relays WHERE id = ?", [$relay['id']]);
}

/**
 * Subscribe a new bot to all existing relays.
 */
function subscribe_new_account_to_relays(array $account): void
{
    $urls = get_relay_urls();
    foreach ($urls as $url) {
        subscribe_account_to_relay($account, $url);
    }
}

/**
 * Unsubscribe a bot from all relays (call before deleting the account).
 */
function unsubscribe_account_from_all_relays(array $account): void
{
    $relays = db_all(
        "SELECT * FROM relays WHERE account_id = ?",
        [$account['id']]
    );
    foreach ($relays as $relay) {
        $relay['username']    = $account['username'];
        $relay['private_key'] = $account['private_key'];
        $relay['public_key']  = $account['public_key'];
        unsubscribe_account_from_relay($relay, $relay['inbox_url']);
    }
}

/**
 * Build a Follow activity for a relay (object = AP_PUBLIC).
 */
function build_relay_follow(array $account): array
{
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/follows/' . generate_uuid(),
        'type'     => 'Follow',
        'actor'    => actor_url($account['username']),
        'object'   => AP_PUBLIC,
    ];
}

/**
 * Build an Undo{Follow} activity for a relay.
 */
function build_relay_unfollow(array $account, string $followActivityId): array
{
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => actor_url($account['username']) . '/unfollows/' . generate_uuid(),
        'type'     => 'Undo',
        'actor'    => actor_url($account['username']),
        'object'   => [
            'id'     => $followActivityId,
            'type'   => 'Follow',
            'actor'  => actor_url($account['username']),
            'object' => AP_PUBLIC,
        ],
    ];
}
