<?php
declare(strict_types=1);

require_once LIB_PATH . '/activity.php';
require_once LIB_PATH . '/crypto.php';
require_once LIB_PATH . '/logger.php';
require_once LIB_PATH . '/autolink.php';
require_once LIB_PATH . '/media_helper.php';
require_once LIB_PATH . '/deliver.php';
require_once LIB_PATH . '/http_sig.php';
require_once LIB_PATH . '/relay.php';
require_once BASE_PATH . '/admin/auth.php';

$adminPath = trim($_GET['_admin_path'] ?? '', '/');
$adminSegs = $adminPath !== '' ? explode('/', $adminPath) : [];
$adminPage = $adminSegs[0] ?? '';

// Setup: no admin password configured
if (admin_needs_setup()) {
    if ($adminPage === 'setup' && is_post()) {
        csrf_verify();
        $pw1 = $_POST['password'] ?? '';
        $pw2 = $_POST['password2'] ?? '';
        $domain = trim($_POST['domain'] ?? '');

        $errors = [];
        if (strlen($pw1) < 8)     $errors[] = 'Password must be at least 8 characters.';
        if ($pw1 !== $pw2)        $errors[] = 'Passwords do not match.';
        if (empty($domain))       $errors[] = 'Domain is required.';

        if (!$errors) {
            db_set_setting('admin_password_hash', password_hash($pw1, PASSWORD_BCRYPT));
            db_set_setting('domain', $domain);
            header('Location: ' . admin_url('login'));
            exit;
        }
    }
    require BASE_PATH . '/templates/admin/setup.php';
    exit;
}

// Login / logout
if ($adminPage === 'login') {
    if (is_post()) {
        csrf_verify();
        if (admin_login($_POST['password'] ?? '')) {
            header('Location: ' . admin_url());
            exit;
        }
        $loginError = 'Incorrect password.';
    }
    require BASE_PATH . '/templates/admin/login.php';
    exit;
}

if ($adminPage === 'logout') {
    admin_logout();
    header('Location: ' . admin_url('login'));
    exit;
}

admin_require_auth();

match ($adminPage) {
    'bots'      => require BASE_PATH . '/admin/bots.php',
    'post'      => require BASE_PATH . '/admin/post.php',
    'social'    => require BASE_PATH . '/admin/social.php',
'move'      => require BASE_PATH . '/admin/move.php',
    'logs'      => require BASE_PATH . '/admin/logs.php',
    'settings'  => require BASE_PATH . '/admin/settings.php',
    default     => require BASE_PATH . '/templates/admin/dashboard.php',
};
