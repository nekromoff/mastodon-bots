<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('LIB_PATH',  BASE_PATH . '/lib');

// Lock in the front-controller's SCRIPT_NAME before any require() changes it.
// site_url() uses SCRIPT_NAME to compute the base path; without this, when PHP's
// dev server dispatches through included files (e.g. admin/index.php) SCRIPT_NAME
// becomes /admin/index.php and admin_url() doubles the /admin/ prefix.
if (php_sapi_name() === 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require_once LIB_PATH . '/db.php';
require_once LIB_PATH . '/helpers.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), bluetooth=(), serial=(), fullscreen=(), accelerometer=(), gyroscope=(), magnetometer=(), notifications=(), push=(), interest-cohort=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'none'; frame-ancestors 'none'");

// Dev server URL routing: map REQUEST_URI to _route + params (Apache does this via .htaccess in production)
if (!isset($_GET['_route']) && php_sapi_name() === 'cli-server') {
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri  = '/' . ltrim($uri, '/');

    // Serve static files directly
    $static = BASE_PATH . $uri;
    if ($uri !== '/' && is_file($static)) {
        $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff2'=> 'font/woff2',
            'woff' => 'font/woff',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($static);
        exit;
    }

    // Map URI patterns to _route and query params
    if (preg_match('#^/admin(/(.*))?$#', $uri, $m)) {
        $_GET['_route'] = 'admin';
        if (!empty($m[2])) $_GET['_admin_path'] = $m[2];
    } elseif ($uri === '/.well-known/webfinger') {
        $_GET['_route'] = 'webfinger';
    } elseif ($uri === '/.well-known/nodeinfo') {
        $_GET['_route'] = 'nodeinfo_discovery';
    } elseif ($uri === '/nodeinfo/2.0') {
        $_GET['_route'] = 'nodeinfo';
    } elseif ($uri === '/inbox') {
        $_GET['_route'] = 'shared_inbox';
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/inbox$#', $uri, $m)) {
        $_GET['_route'] = 'inbox'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/outbox$#', $uri, $m)) {
        $_GET['_route'] = 'outbox'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/followers$#', $uri, $m)) {
        $_GET['_route'] = 'followers'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/following$#', $uri, $m)) {
        $_GET['_route'] = 'following'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/featured_tags$#', $uri, $m)) {
        $_GET['_route'] = 'featured_tags'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/statuses/([a-zA-Z0-9_-]+)/quotes/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['_route'] = 'quote_authorization'; $_GET['username'] = $m[1]; $_GET['status_id'] = $m[2]; $_GET['quote_uuid'] = $m[3];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)/statuses/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['_route'] = 'status'; $_GET['username'] = $m[1]; $_GET['status_id'] = $m[2];
    } elseif (preg_match('#^/users/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['_route'] = 'actor'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/@([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['_route'] = 'status'; $_GET['username'] = $m[1]; $_GET['status_id'] = $m[2];
    } elseif (preg_match('#^/@([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['_route'] = 'profile'; $_GET['username'] = $m[1];
    } elseif (preg_match('#^/tags/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
        $_GET['_route'] = 'tag'; $_GET['tag'] = $m[1];
    } elseif (preg_match('#^/api/(.*)$#', $uri, $m)) {
        $_GET['_route'] = 'api'; $_GET['_api_path'] = $m[1];
    } elseif ($uri === '/media/upload') {
        $_GET['_route'] = 'media_upload';
    } elseif ($uri === '/') {
        $_GET['_route'] = 'home';
    }
}

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
