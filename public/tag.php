<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';

$tag = strtolower(trim($_GET['tag'] ?? ''));
if (empty($tag)) {
    error_response(400, 'Missing tag');
}

if (wants_activitypub()) {
    // Return AP OrderedCollection for hashtag
    $total = (int)(db_get(
        "SELECT COUNT(*) as c FROM hashtags h
         JOIN posts p ON p.id = h.post_id
         WHERE h.tag = ? AND p.deleted_at IS NULL AND p.visibility = 'public'",
        [$tag]
    )['c'] ?? 0);

    $tagUrl = base_url() . '/tags/' . urlencode($tag);
    $col    = build_ordered_collection($tagUrl, $total);
    json_response($col);
}

// HTML tag page
$posts = db_all(
    "SELECT p.*,
            (SELECT COUNT(*) FROM likes  WHERE post_id = p.id) AS likes_count,
            (SELECT COUNT(*) FROM boosts WHERE post_id = p.id) AS boosts_count,
            (SELECT COUNT(*) FROM quotes WHERE post_id = p.id) AS quotes_count,
            a.username, a.display_name, a.avatar_path
     FROM hashtags h
     JOIN posts p ON p.id = h.post_id
     JOIN accounts a ON a.id = p.account_id
     WHERE h.tag = ? AND p.deleted_at IS NULL AND p.visibility = 'public'
     ORDER BY p.published_at DESC
     LIMIT 40",
    [$tag]
);

foreach ($posts as &$post) {
    $post['attachments'] = load_post_attachments((int)$post['id']);
}
unset($post);

$pageTitle  = '#' . h($tag);
$robotsMeta = 'noindex,follow';
require BASE_PATH . '/templates/tag.php';
