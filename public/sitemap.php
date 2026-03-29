<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

// 1. Determine the latest modification for ETag/Last-Modified calculation
$lastPostQuery = db_get("SELECT MAX(COALESCE(updated_at, published_at)) as last_mod FROM posts WHERE visibility = 'public' AND deleted_at IS NULL");
$lastAccountQuery = db_get("SELECT MAX(created_at) as last_mod FROM accounts WHERE indexable = 1 AND noindex = 0");

$lastPostMod = $lastPostQuery['last_mod'] ?? '1970-01-01 00:00:00';
$lastAccountMod = $lastAccountQuery['last_mod'] ?? '1970-01-01 00:00:00';

$maxModStr = max($lastPostMod, $lastAccountMod);
$maxModTs = strtotime($maxModStr) ?: time();

// Generate ETag based on last modification and counts to detect changes reliably
$countPost = db_get("SELECT COUNT(*) as cnt FROM posts WHERE visibility = 'public' AND deleted_at IS NULL")['cnt'] ?? 0;
$countAccount = db_get("SELECT COUNT(*) as cnt FROM accounts WHERE indexable = 1 AND noindex = 0")['cnt'] ?? 0;

$etag = md5($maxModStr . $countPost . $countAccount);

// 2. Handle Caching and Revalidation
header('Cache-Control: public, max-age=21600, must-revalidate'); // 6 hours
header('ETag: "' . $etag . '"');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $maxModTs) . ' GMT');

// Revalidation check
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
    http_response_code(304);
    exit;
}

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') >= $maxModTs) {
    http_response_code(304);
    exit;
}

// 3. Generate the Sitemap
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// Home Page
// site_url('/') is for friendly URLs, base_url().'/' ensures full domain
echo '  <url>' . PHP_EOL;
echo '    <loc>' . h(base_url() . '/') . '</loc>' . PHP_EOL;
echo '    <changefreq>daily</changefreq>' . PHP_EOL;
echo '    <priority>1.0</priority>' . PHP_EOL;
echo '  </url>' . PHP_EOL;

// Profiles (Requested: daily)
$accounts = db_all("SELECT username, created_at FROM accounts WHERE indexable = 1 AND noindex = 0 ORDER BY created_at DESC");
foreach ($accounts as $acc) {
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . h(profile_url($acc['username'])) . '</loc>' . PHP_EOL;
    echo '    <lastmod>' . date('Y-m-d', strtotime($acc['created_at'])) . '</lastmod>' . PHP_EOL;
    echo '    <changefreq>daily</changefreq>' . PHP_EOL;
    echo '    <priority>0.8</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}

// Posts (Recommended: monthly)
// Use the UUID part of activity_id for URLs, as required
$posts = db_all("
    SELECT p.activity_id, a.username, COALESCE(p.updated_at, p.published_at) as last_mod 
    FROM posts p 
    JOIN accounts a ON p.account_id = a.id 
    WHERE p.visibility = 'public' AND p.deleted_at IS NULL 
    ORDER BY p.published_at DESC
");
foreach ($posts as $post) {
    $statusId = basename($post['activity_id']);
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . h(profile_url($post['username']) . '/' . $statusId) . '</loc>' . PHP_EOL;
    echo '    <lastmod>' . date('Y-m-d', strtotime($post['last_mod'])) . '</lastmod>' . PHP_EOL;
    echo '    <changefreq>monthly</changefreq>' . PHP_EOL;
    echo '    <priority>0.5</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}

echo '</urlset>' . PHP_EOL;
