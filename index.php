<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('LIB_PATH',  BASE_PATH . '/lib');

require_once LIB_PATH . '/db.php';
require_once LIB_PATH . '/helpers.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), bluetooth=(), serial=(), fullscreen=(), accelerometer=(), gyroscope=(), magnetometer=(), notifications=(), push=(), interest-cohort=()');

$route = $_GET['_route'] ?? 'home';

$routes = [
    'webfinger'          => 'public/webfinger.php',
    'nodeinfo_discovery' => 'public/nodeinfo.php',
    'nodeinfo'           => 'public/nodeinfo.php',
    'actor'              => 'public/actor.php',
    'status'             => 'public/status.php',
    'quote_authorization' => 'public/quote_authorization.php',
    'profile'            => 'public/actor.php',
    'inbox'              => 'public/inbox.php',
    'shared_inbox'       => 'public/shared_inbox.php',
    'outbox'             => 'public/outbox.php',
    'followers'          => 'public/followers.php',
    'following'          => 'public/following.php',
    'featured_tags'      => 'public/featured_tags.php',
    'tag'                => 'public/tag.php',
    'media_upload'       => 'media.php',
    'api'                => 'api.php',
    'admin'              => 'admin/index.php',
    'home'               => 'public/home.php',
];

$file = $routes[$route] ?? null;

if ($file && file_exists(BASE_PATH . '/' . $file)) {
    require BASE_PATH . '/' . $file;
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n";
}
