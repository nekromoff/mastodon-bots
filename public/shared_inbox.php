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

$to  = (array)($activity['to']  ?? []);
$cc  = (array)($activity['cc']  ?? []);
$all = array_unique([...$to, ...$cc]);

// Find all local accounts that should receive this activity
$localAccounts = db_all("SELECT * FROM accounts", []);

foreach ($localAccounts as $account) {
    // Skip blocked senders
    $blocked = db_get(
        "SELECT id FROM blocks WHERE account_id = ? AND target_uri = ?",
        [$account['id'], $actorUri]
    );
    if ($blocked) continue;

    $followersUri = followers_url($account['username']);
    $actorUriLocal = actor_url($account['username']);

    // Dispatch if account is addressed (public, followers, or direct)
    $relevant = in_array(AP_PUBLIC, $all, true)
             || in_array($followersUri, $all, true)
             || in_array($actorUriLocal, $all, true);

    if (!$relevant) {
        // Also dispatch if this account follows the sender
        $isFollowing = db_get(
            "SELECT id FROM following WHERE account_id = ? AND following_uri = ? AND accepted = 1",
            [$account['id'], $actorUri]
        );
        if (!$isFollowing) continue;
    }

    dispatch_inbox_activity($account, $actor, $activity, $body, $actorUri);
}

http_response_code(202);
header('Content-Type: application/json');
echo '{"status":"accepted"}';
exit;

function dispatch_inbox_activity(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    match ($activity['type']) {
        'Follow'   => shared_handle_follow($account, $actor, $activity, $body, $actorUri),
        'Accept'   => shared_handle_accept($account, $actor, $activity, $body, $actorUri),
        'Reject'   => shared_handle_reject($account, $actor, $activity, $body, $actorUri),
        'Undo'     => shared_handle_undo($account, $actor, $activity, $body, $actorUri),
        'Like'     => shared_handle_like($account, $actor, $activity, $body, $actorUri),
        'Announce' => shared_handle_announce($account, $actor, $activity, $body, $actorUri),
        'Create'   => shared_handle_create($account, $actor, $activity, $body, $actorUri),
        'Delete'   => shared_handle_delete($account, $actor, $activity, $body, $actorUri),
        'Block'        => shared_handle_block($account, $actor, $activity, $body, $actorUri),
        'Move'         => shared_handle_move($account, $actor, $activity, $body, $actorUri),
        'QuoteRequest' => shared_handle_quote_request($account, $actor, $activity, $actorUri),
        default        => null,
    };
}

function shared_handle_follow(array $account, array $actor, array $activity, string $body, string $actorUri): void {
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

function shared_handle_accept(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object   = $activity['object'] ?? '';
    $followId = is_array($object) ? ($object['id'] ?? '') : $object;

    if (!empty($followId)) {
        db_run(
            "UPDATE following SET accepted = 1 WHERE account_id = ? AND follow_activity_id = ?",
            [$account['id'], $followId]
        );
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

function shared_handle_reject(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object   = $activity['object'] ?? '';
    $followId = is_array($object) ? ($object['id'] ?? '') : $object;

    if (!empty($followId)) {
        db_run(
            "DELETE FROM following WHERE account_id = ? AND follow_activity_id = ?",
            [$account['id'], $followId]
        );
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

function shared_handle_undo(array $account, array $actor, array $activity, string $body, string $actorUri): void {
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
                $post = db_get("SELECT id FROM posts WHERE activity_id = ? AND account_id = ?", [$objectId, $account['id']]);
                if ($post) {
                    db_run("DELETE FROM likes WHERE post_id = ? AND actor_uri = ?", [$post['id'], $actor['id']]);
                    log_activity((int)$account['id'], 'in', 'Undo', $body, $actorUri, '', 'received');
                }
            }
            break;
        case 'Announce':
            $objectId = $object['object'] ?? '';
            if ($objectId) {
                $post = db_get("SELECT id FROM posts WHERE activity_id = ? AND account_id = ?", [$objectId, $account['id']]);
                if ($post) {
                    db_run("DELETE FROM boosts WHERE post_id = ? AND actor_uri = ?", [$post['id'], $actor['id']]);
                    log_activity((int)$account['id'], 'in', 'Undo', $body, $actorUri, '', 'received');
                }
            }
            break;
    }
}

function shared_handle_like(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $objectId = $activity['object'] ?? '';
    if (empty($objectId) || !is_string($objectId)) return;
    $post = db_get("SELECT id FROM posts WHERE activity_id = ? AND account_id = ?", [$objectId, $account['id']]);
    if (!$post) return;
    db_run("INSERT OR IGNORE INTO likes (post_id, actor_uri) VALUES (?, ?)", [$post['id'], $actor['id']]);
    log_activity((int)$account['id'], 'in', 'Like', $body, $actorUri, '', 'received');
}

function shared_handle_announce(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $objectId = $activity['object'] ?? '';
    if (empty($objectId) || !is_string($objectId)) return;
    $post = db_get("SELECT id FROM posts WHERE activity_id = ? AND account_id = ?", [$objectId, $account['id']]);
    if (!$post) return;
    db_run("INSERT OR IGNORE INTO boosts (post_id, actor_uri, activity_id) VALUES (?, ?, ?)", [$post['id'], $actor['id'], $activity['id'] ?? '']);
    log_activity((int)$account['id'], 'in', 'Announce', $body, $actorUri, '', 'received');
}

function shared_handle_create(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $obj = $activity['object'] ?? [];
    if (!is_array($obj)) return;

    $inReplyTo = $obj['inReplyTo'] ?? '';
    $quoteUrl  = $obj['quoteUrl'] ?? $obj['_misskey_quote'] ?? '';
    $refId = $inReplyTo ?: $quoteUrl;
    if (empty($refId)) return;

    $post = db_get("SELECT id, activity_id FROM posts WHERE activity_id = ? AND account_id = ?", [$refId, $account['id']]);
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
}

function shared_handle_delete(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $object   = $activity['object'] ?? '';
    $objectId = is_array($object) ? ($object['id'] ?? '') : $object;
    if (empty($objectId)) return;

    if ($objectId === $actor['id']) {
        $wasFollower  = db_get("SELECT id FROM followers WHERE account_id = ? AND follower_uri = ?", [$account['id'], $actor['id']]);
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

function shared_handle_block(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $objectUri = is_string($activity['object']) ? $activity['object'] : ($activity['object']['id'] ?? '');
    if ($objectUri === actor_url($account['username'])) {
        db_run("DELETE FROM following WHERE account_id = ? AND following_uri = ?", [$account['id'], $actor['id']]);
        log_activity((int)$account['id'], 'in', 'Block', $body, $actorUri, '', 'received');
    }
}

function shared_handle_move(array $account, array $actor, array $activity, string $body, string $actorUri): void {
    $newUri = $activity['target'] ?? '';
    if (empty($newUri)) return;
    $stmt = db_run(
        "UPDATE following SET following_uri = ? WHERE account_id = ? AND following_uri = ?",
        [$newUri, $account['id'], $actor['id']]
    );
    if ($stmt->rowCount() > 0) {
        log_activity((int)$account['id'], 'in', 'Move', $body, $actorUri, '', 'received');
    }
}

function shared_handle_quote_request(array $account, array $actor, array $activity, string $actorUri): void {
    $quotedPostUri = is_array($activity['object']) ? ($activity['object']['object'] ?? '') : ($activity['object'] ?? '');
    if (empty($quotedPostUri)) return;

    $post = db_get("SELECT id, activity_id FROM posts WHERE activity_id = ? AND account_id = ? AND deleted_at IS NULL", [$quotedPostUri, $account['id']]);
    if (!$post) return;

    // Fosstodon puts it in activity['instrument']['id']; some servers put it in activity['object']['id']
    $quotingPostUri = $activity['instrument']['id']
        ?? (is_array($activity['object']) ? ($activity['object']['id'] ?? '') : '');

    $stampUuid = generate_uuid();
    $stmt = db_run(
        "INSERT OR IGNORE INTO quote_authorizations (post_id, stamp_uuid, quoting_post_uri) VALUES (?, ?, ?)",
        [$post['id'], $stampUuid, $quotingPostUri]
    );
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
