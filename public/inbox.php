<?php
declare(strict_types=1);

require_once LIB_PATH . '/http_sig.php';
require_once LIB_PATH . '/activity.php';
require_once LIB_PATH . '/deliver.php';
require_once LIB_PATH . '/logger.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response(405, 'Method Not Allowed');
}

$username = sanitize_username($_GET['username'] ?? '');
$account  = $username ? get_account_by_username($username) : null;

if (!$account) {
    error_response(404, 'Account not found');
}

$body    = request_body();
$headers = get_all_request_headers();
$path    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
$method  = $_SERVER['REQUEST_METHOD'];

// Verify HTTP Signature
$actor = http_sig_verify($headers, $method, $path, $body);
if ($actor === null) {
    error_response(401, 'Invalid HTTP Signature');
}

$activity = request_json();
if (empty($activity['type'])) {
    error_response(400, 'Missing activity type');
}

$actorUri = $actor['id'] ?? ($activity['actor'] ?? '');

// Check if sender is blocked
$blocked = db_get(
    "SELECT id FROM blocks WHERE account_id = ? AND target_uri = ?",
    [$account['id'], $actorUri]
);
if ($blocked) {
    http_response_code(200);
    exit;
}

// Route activity types
match ($activity['type']) {
    'Follow'   => handle_follow($account, $actor, $activity, $body, $actorUri),
    'Accept'   => handle_accept_activity($account, $actor, $activity, $body, $actorUri),
    'Reject'   => handle_reject_activity($account, $actor, $activity, $body, $actorUri),
    'Undo'     => handle_undo($account, $actor, $activity, $body, $actorUri),
    'Like'     => handle_like($account, $actor, $activity, $body, $actorUri),
    'Announce' => handle_announce($account, $actor, $activity, $body, $actorUri),
    'Create'   => handle_create($account, $actor, $activity, $body, $actorUri),
    'Update'   => handle_update_activity($account, $actor, $activity),
    'Delete'   => handle_delete_activity($account, $actor, $activity, $body, $actorUri),
    'Block'        => handle_block_activity($account, $actor, $activity, $body, $actorUri),
    'Move'         => handle_move_activity($account, $actor, $activity, $body, $actorUri),
    'QuoteRequest' => handle_quote_request($account, $actor, $activity, $actorUri),
    default        => null,
};

http_response_code(202);
header('Content-Type: application/json');
echo '{"status":"accepted"}';
exit;

// ---- Handlers ----

function handle_follow(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $inboxUrl    = $actor['inbox'] ?? '';
    $sharedInbox = $actor['endpoints']['sharedInbox'] ?? '';

    db_run(
        "INSERT OR IGNORE INTO followers
            (account_id, follower_uri, follower_inbox, shared_inbox, accepted, follow_activity_id)
         VALUES (?, ?, ?, ?, 0, ?)",
        [$account['id'], $actorUri, $inboxUrl, $sharedInbox, $activity['id'] ?? '']
    );

    log_activity((int)$account['id'], 'in', 'Follow', $body, $actorUri, '', 'received');

    if (!$account['manually_approves_followers']) {
        db_run(
            "UPDATE followers SET accepted = 1 WHERE account_id = ? AND follower_uri = ?",
            [$account['id'], $actorUri]
        );
        $accept = build_accept_follow($account, $activity);
        deliver_to_actor($accept, $account, $actorUri);
    }
}

function handle_accept_activity(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object   = $activity['object'] ?? '';
    $followId = is_array($object) ? ($object['id'] ?? '') : $object;

    if (!empty($followId)) {
        db_run(
            "UPDATE following SET accepted = 1 WHERE account_id = ? AND follow_activity_id = ?",
            [$account['id'], $followId]
        );
        // Also check if this Accept is for a relay subscription
        db_run(
            "UPDATE relays SET state = 'accepted' WHERE follow_activity_id = ? AND account_id = ?",
            [$followId, $account['id']]
        );
    } else {
        db_run(
            "UPDATE following SET accepted = 1 WHERE account_id = ? AND following_uri = ?",
            [$account['id'], $actor['id']]
        );
    }
    log_activity((int)$account['id'], 'in', 'Accept', $body, $actorUri, '', 'received');
}

function handle_reject_activity(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object   = $activity['object'] ?? '';
    $followId = is_array($object) ? ($object['id'] ?? '') : $object;

    if (!empty($followId)) {
        db_run(
            "DELETE FROM following WHERE account_id = ? AND follow_activity_id = ?",
            [$account['id'], $followId]
        );
        // Also check if this Reject is for a relay subscription
        db_run(
            "UPDATE relays SET state = 'rejected' WHERE follow_activity_id = ? AND account_id = ?",
            [$followId, $account['id']]
        );
    } else {
        db_run(
            "DELETE FROM following WHERE account_id = ? AND following_uri = ?",
            [$account['id'], $actor['id']]
        );
    }
    log_activity((int)$account['id'], 'in', 'Reject', $body, $actorUri, '', 'received');
}

function handle_undo(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object = $activity['object'] ?? [];

    if (is_string($object)) return;

    $type = $object['type'] ?? '';

    switch ($type) {
        case 'Follow':
            db_run(
                "DELETE FROM followers WHERE account_id = ? AND follower_uri = ?",
                [$account['id'], $actor['id']]
            );
            log_activity((int)$account['id'], 'in', 'Undo', $body, $actorUri, '', 'received');
            break;

        case 'Like':
            $objectId = $object['object'] ?? '';
            if ($objectId) {
                $post = db_get("SELECT id FROM posts WHERE activity_id = ?", [$objectId]);
                if ($post) {
                    db_run("DELETE FROM likes WHERE post_id = ? AND actor_uri = ?", [$post['id'], $actor['id']]);
                    log_activity((int)$account['id'], 'in', 'Undo', $body, $actorUri, '', 'received');
                }
            }
            break;

        case 'Announce':
            $objectId = $object['object'] ?? '';
            if ($objectId) {
                $post = db_get("SELECT id FROM posts WHERE activity_id = ?", [$objectId]);
                if ($post) {
                    db_run("DELETE FROM boosts WHERE post_id = ? AND actor_uri = ?", [$post['id'], $actor['id']]);
                    log_activity((int)$account['id'], 'in', 'Undo', $body, $actorUri, '', 'received');
                }
            }
            break;
    }
}

function handle_like(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $objectId = $activity['object'] ?? '';
    if (empty($objectId) || !is_string($objectId)) return;

    $post = db_get("SELECT id FROM posts WHERE activity_id = ?", [$objectId]);
    if (!$post) return;

    db_run("INSERT OR IGNORE INTO likes (post_id, actor_uri) VALUES (?, ?)", [$post['id'], $actor['id']]);
    log_activity((int)$account['id'], 'in', 'Like', $body, $actorUri, '', 'received');
}

function handle_announce(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $objectId = $activity['object'] ?? '';
    if (empty($objectId) || !is_string($objectId)) return;

    $post = db_get("SELECT id FROM posts WHERE activity_id = ?", [$objectId]);
    if (!$post) return;

    db_run(
        "INSERT OR IGNORE INTO boosts (post_id, actor_uri, activity_id) VALUES (?, ?, ?)",
        [$post['id'], $actor['id'], $activity['id'] ?? '']
    );
    log_activity((int)$account['id'], 'in', 'Announce', $body, $actorUri, '', 'received');
}

function handle_create(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $obj = $activity['object'] ?? [];
    if (!is_array($obj)) return;

    // Log only if it's a reply to or quote of one of our posts
    $inReplyTo = $obj['inReplyTo'] ?? '';
    $quoteUrl  = $obj['quoteUrl'] ?? $obj['_misskey_quote'] ?? '';

    $refId = $inReplyTo ?: $quoteUrl;
    if (empty($refId)) return;

    $post = db_get("SELECT id, activity_id FROM posts WHERE activity_id = ?", [$refId]);
    if (!$post) return;

    if (!empty($quoteUrl) && $quoteUrl === $refId) {
        db_run(
            "INSERT OR IGNORE INTO quotes (post_id, actor_uri, activity_id) VALUES (?, ?, ?)",
            [$post['id'], $actorUri, $activity['id'] ?? '']
        );

        // Auto-generate QuoteAuthorization stamp and notify the quoting server (FEP-044f)
        $quotePostUri = $obj['id'] ?? '';
        if ($quotePostUri) {
            $stampUuid = generate_uuid();
            $stmt = db_run(
                "INSERT OR IGNORE INTO quote_authorizations (post_id, stamp_uuid, quoting_post_uri) VALUES (?, ?, ?)",
                [$post['id'], $stampUuid, $quotePostUri]
            );
            // Fetch existing UUID if this quote was already stamped
            if ($stmt->rowCount() === 0) {
                $existing  = db_get(
                    "SELECT stamp_uuid FROM quote_authorizations WHERE post_id = ? AND quoting_post_uri = ?",
                    [$post['id'], $quotePostUri]
                );
                $stampUuid = $existing['stamp_uuid'] ?? $stampUuid;
            }
            $stampUrl = $post['activity_id'] . '/quotes/' . $stampUuid;
            // Send Accept{Create} with stamp URL so quoting server can set quoteAuthorization
            $accept = build_accept_quote_request($account, $activity, $stampUrl);
            deliver_to_actor($accept, $account, $actorUri);
        }
    }

    log_activity((int)$account['id'], 'in', 'Create', $body, $actorUri, '', 'received');

    if (!empty($inReplyTo)) {
        $attachments = [];
        foreach (($obj['attachment'] ?? []) as $att) {
            $attUrl = is_array($att) ? ($att['url'] ?? '') : (string)$att;
            if ($attUrl !== '') $attachments[] = $attUrl;
        }
        fire_webhook('reply', [
            'bot'             => $account['username'],
            'actor_uri'       => $actorUri,
            'reply_url'       => $obj['id'] ?? '',
            'in_reply_to_url' => $inReplyTo,
            'content'         => $obj['content'] ?? '',
            'summary'         => $obj['summary'] ?? '',
            'attachments'     => $attachments,
        ]);
    }
}

function handle_update_activity(array $_account, array $_actor, array $_activity): void {
    // Remote post updates are not stored locally — nothing to log
}

function handle_delete_activity(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object   = $activity['object'] ?? '';
    $objectId = is_array($object) ? ($object['id'] ?? '') : $object;

    if (empty($objectId)) return;

    // Handle actor self-deletion (account gone)
    if ($objectId === $actor['id']) {
        $wasFollower = db_get("SELECT id FROM followers WHERE account_id = ? AND follower_uri = ?", [$account['id'], $actor['id']]);
        $wasFollowing = db_get("SELECT id FROM following WHERE account_id = ? AND following_uri = ?", [$account['id'], $actor['id']]);
        db_run("DELETE FROM followers WHERE account_id = ? AND follower_uri = ?", [$account['id'], $actor['id']]);
        db_run("DELETE FROM following WHERE account_id = ? AND following_uri = ?", [$account['id'], $actor['id']]);
        if ($wasFollower || $wasFollowing) {
            log_activity((int)$account['id'], 'in', 'Delete', $body, $actorUri, '', 'received');
        }
    } else {
        // Remote post deletion — clean up any quote record for this activity
        db_run("DELETE FROM quotes WHERE activity_id = ? AND actor_uri = ?", [$objectId, $actorUri]);
    }
}

function handle_block_activity(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $objectUri = is_string($activity['object']) ? $activity['object'] : ($activity['object']['id'] ?? '');
    if ($objectUri === actor_url($account['username'])) {
        db_run(
            "DELETE FROM following WHERE account_id = ? AND following_uri = ?",
            [$account['id'], $actor['id']]
        );
        log_activity((int)$account['id'], 'in', 'Block', $body, $actorUri, '', 'received');
    }
}

function handle_move_activity(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $newUri = $activity['target'] ?? '';
    if (empty($newUri)) return;

    db_run(
        "UPDATE following SET following_uri = ? WHERE account_id = ? AND following_uri = ?",
        [$newUri, $account['id'], $actor['id']]
    );
    log_activity((int)$account['id'], 'in', 'Move', $body, $actorUri, '', 'received');
}

function handle_quote_request(array $account, array $actor, array $activity, string $actorUri): void {
    // The quoted post URI is in activity['object']
    $quotedPostUri = is_array($activity['object']) ? ($activity['object']['object'] ?? '') : ($activity['object'] ?? '');
    if (empty($quotedPostUri)) return;

    // Only accept if the quoted post belongs to this account
    $post = db_get("SELECT id, activity_id FROM posts WHERE activity_id = ? AND account_id = ? AND deleted_at IS NULL", [$quotedPostUri, $account['id']]);
    if (!$post) return;

    // The quoting post URI (interactingObject in the stamp)
    // Fosstodon puts it in activity['instrument']['id']; some servers put it in activity['object']['id']
    $quotingPostUri = $activity['instrument']['id']
        ?? (is_array($activity['object']) ? ($activity['object']['id'] ?? '') : '');

    // Build and persist the stamp so it's publicly dereferenceable (FEP-044f)
    $stampUuid = generate_uuid();
    $stmt = db_run(
        "INSERT OR IGNORE INTO quote_authorizations (post_id, stamp_uuid, quoting_post_uri) VALUES (?, ?, ?)",
        [$post['id'], $stampUuid, $quotingPostUri]
    );
    // If a stamp already existed for this quote, use its UUID (not the freshly generated one)
    if ($stmt->rowCount() === 0) {
        $existing  = db_get(
            "SELECT stamp_uuid FROM quote_authorizations WHERE post_id = ? AND quoting_post_uri = ?",
            [$post['id'], $quotingPostUri]
        );
        $stampUuid = $existing['stamp_uuid'] ?? $stampUuid;
    }
    $stampUrl = $post['activity_id'] . '/quotes/' . $stampUuid;

    $accept = build_accept_quote_request($account, $activity, $stampUrl);
    deliver_to_actor($accept, $account, $actorUri);
}
