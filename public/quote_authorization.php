<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';

$username  = sanitize_username($_GET['username']  ?? '');
// Only allow UUID-safe characters (hex digits + hyphens) to prevent injection or path confusion
$statusId  = preg_replace('/[^a-f0-9-]/i', '', $_GET['status_id']  ?? '');
$quoteUuid = preg_replace('/[^a-f0-9-]/i', '', $_GET['quote_uuid'] ?? '');

if (empty($username) || empty($statusId) || empty($quoteUuid)) {
    error_response(404, 'Not found');
}

$account = get_account_by_username($username);
if (!$account) {
    error_response(404, 'Not found');
}

$activityId = actor_url($username) . '/statuses/' . $statusId;
$post = db_get(
    "SELECT id, activity_id FROM posts WHERE activity_id = ? AND account_id = ? AND deleted_at IS NULL",
    [$activityId, $account['id']]
);
if (!$post) {
    error_response(404, 'Not found');
}

$stamp = db_get(
    "SELECT stamp_uuid, quoting_post_uri FROM quote_authorizations WHERE post_id = ? AND stamp_uuid = ?",
    [$post['id'], $quoteUuid]
);
if (!$stamp) {
    error_response(404, 'Not found');
}

$stampUrl = $post['activity_id'] . '/quotes/' . $quoteUuid;

$obj = [
    '@context' => [
        'https://www.w3.org/ns/activitystreams',
        [
            'QuoteAuthorization' => 'https://w3id.org/fep/044f#QuoteAuthorization',
            'gts'                => 'https://gotosocial.org/ns#',
            'interactingObject'  => ['@id' => 'gts:interactingObject', '@type' => '@id'],
            'interactionTarget'  => ['@id' => 'gts:interactionTarget', '@type' => '@id'],
        ],
    ],
    'type'              => 'QuoteAuthorization',
    'id'                => $stampUrl,
    'attributedTo'      => actor_url($account['username']),
    'interactingObject' => $stamp['quoting_post_uri'],
    'interactionTarget' => $post['activity_id'],
];

json_response($obj);
