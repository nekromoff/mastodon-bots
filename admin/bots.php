<?php
declare(strict_types=1);

// $adminSegs: ['bots'], ['bots','create'], ['bots','N'], ['bots','N','edit'], ['bots','N','delete']
$botId     = isset($adminSegs[1]) && is_numeric($adminSegs[1]) ? (int)$adminSegs[1] : null;
$botAction = $adminSegs[2] ?? ($adminSegs[1] === 'create' ? 'create' : '');

// List view is now the dashboard
if ($botAction === '' && !$botId) {
    redirect(admin_url());
}

// Create new bot
if ($botAction === 'create' && is_post()) {
    csrf_verify();
    $username    = sanitize_username($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');
    $password    = trim($_POST['bot_password'] ?? '');

    $errors = [];
    if (empty($username))              $errors[] = 'Username is required.';
    if (strlen($username) < 2)         $errors[] = 'Username must be at least 2 characters.';
    if (get_account_by_username($username)) $errors[] = 'Username already taken.';
    if (strlen($password) < 6)         $errors[] = 'Bot password must be at least 6 characters.';

    if (!$errors) {
        $keys = generate_rsa_keypair();
        db_run(
            "INSERT INTO accounts
                (username, display_name, bio, public_key, private_key, password_hash)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $username,
                $displayName ?: $username,
                $bio,
                $keys['public'],
                $keys['private'],
                password_hash($password, PASSWORD_BCRYPT),
            ]
        );

        // Create upload directories
        foreach (['avatars', 'headers'] as $dir) {
            $path = BASE_PATH . '/uploads/' . $dir . '/' . $username;
            if (!is_dir($path)) mkdir($path, 0755, true);
        }

        // Subscribe new bot to all existing relays
        $newAccount = get_account_by_username($username);
        if ($newAccount) {
            subscribe_new_account_to_relays($newAccount);
        }

        redirect(admin_url('?created=1'));
    }
}

// Edit bot
if ($botAction === 'edit' && $botId && is_post()) {
    csrf_verify();
    $bot = get_account_by_id($botId);
    if (!$bot) redirect(admin_url('bots'));

    $displayName = trim($_POST['display_name'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');
    $password    = trim($_POST['bot_password'] ?? '');
    $discoverable             = isset($_POST['discoverable']) ? 1 : 0;
    $indexable                = isset($_POST['indexable']) ? 1 : 0;
    $noindex                  = isset($_POST['searchengine_index']) ? 0 : 1;
    $locked                   = isset($_POST['locked']) ? 1 : 0;
    $manuallyApprovesFollowers = isset($_POST['manually_approves_followers']) ? 1 : 0;
    $fediverseCreator          = trim($_POST['fediverse_creator'] ?? '');

    $fieldNames  = $_POST['field_name']  ?? [];
    $fieldValues = $_POST['field_value'] ?? [];
    $profileFields = [];
    for ($i = 0; $i < 4; $i++) {
        $fn = trim($fieldNames[$i]  ?? '');
        $fv = trim($fieldValues[$i] ?? '');
        if ($fn !== '') $profileFields[] = ['name' => $fn, 'value' => $fv];
    }
    $profileFieldsJson = json_encode($profileFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Parse featured hashtags: split on whitespace or commas, strip # prefix, limit 10
    $featuredHashtags = array_values(array_filter(array_map(
        fn($t) => preg_replace('/[^a-zA-Z0-9_]/', '', ltrim(trim($t), '#')),
        preg_split('/[\s,]+/', $_POST['featured_hashtags'] ?? '')
    )));
    $featuredHashtags = array_slice($featuredHashtags, 0, 10);
    $featuredHashtagsJson = json_encode($featuredHashtags, JSON_UNESCAPED_UNICODE);

    $pwHash = $bot['password_hash'];
    if (!empty($password)) {
        $pwHash = password_hash($password, PASSWORD_BCRYPT);
    }

    // Handle avatar upload
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $path = handle_avatar_upload($_FILES['avatar'], $bot['username'], 'avatar');
            db_run("UPDATE accounts SET avatar_path = ? WHERE id = ?", [$path, $botId]);
        } catch (RuntimeException $e) {
            $editError = 'Avatar: ' . $e->getMessage();
        }
    }

    // Handle header upload
    if (!empty($_FILES['header']['name'])) {
        try {
            $path = handle_avatar_upload($_FILES['header'], $bot['username'], 'header');
            db_run("UPDATE accounts SET header_path = ? WHERE id = ?", [$path, $botId]);
        } catch (RuntimeException $e) {
            $editError = ($editError ?? '') . ' Header: ' . $e->getMessage();
        }
    }

    db_run(
        "UPDATE accounts
         SET display_name = ?, bio = ?, password_hash = ?, discoverable = ?, indexable = ?,
             noindex = ?, locked = ?, manually_approves_followers = ?, profile_fields = ?,
             featured_hashtags = ?, fediverse_creator = ?
         WHERE id = ?",
        [$displayName, $bio, $pwHash, $discoverable, $indexable, $noindex, $locked, $manuallyApprovesFollowers, $profileFieldsJson, $featuredHashtagsJson, $fediverseCreator, $botId]
    );

    redirect(admin_url('bots/' . $botId . '?updated=1'));
}

// Delete bot
if ($botAction === 'delete' && $botId && is_post()) {
    csrf_verify();
    $delBot = get_account_by_id($botId);
    if ($delBot) {
        // Unsubscribe from all relays before deleting
        unsubscribe_account_from_all_relays($delBot);
        db_run("DELETE FROM accounts WHERE id = ?", [$botId]);
        foreach (['avatars', 'headers'] as $_dir) {
            $_path = BASE_PATH . '/uploads/' . $_dir . '/' . $delBot['username'];
            if (is_dir($_path)) {
                array_map('unlink', glob($_path . '/*') ?: []);
                rmdir($_path);
            }
        }
    }
    redirect(admin_url('?deleted=1'));
}

$accounts  = get_all_accounts();
$editBot   = $botId ? get_account_by_id($botId) : null;

require BASE_PATH . '/templates/admin/bots.php';
