<?php
$pageTitle = 'Account Move — @' . $account['username'];
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1>Account Move — @<?= h($account['username']) ?></h1>
<div class="section-nav">
  <a href="<?= h(admin_url('bots')) ?>" class="btn btn-secondary btn-sm">← Bots</a>
  <a href="<?= h(admin_url('bots/' . $account['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Edit</a>
  <a href="<?= h(admin_url('post/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">Post</a>
  <a href="<?= h(admin_url('social/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">Social</a>
  <span class="nav-current">Move</span>
</div>

<?php if (!empty($success)): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<!-- Current State -->
<div class="card">
  <h2>Current Status</h2>
  <table>
    <tr><th class="move-table-label">Actor URI</th><td><code><?= h(actor_url($account['username'])) ?></code></td></tr>
    <tr>
      <th>movedTo</th>
      <td>
        <?php if (!empty($account['moved_to'])): ?>
        <a href="<?= h($account['moved_to']) ?>"><?= h($account['moved_to']) ?></a>
        <form method="POST" action="<?= h(admin_url('move/' . $account['id'] . '/clear_moved_to')) ?>" class="inline-form ml-sm">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <button type="submit" class="btn btn-secondary btn-sm">Clear</button>
        </form>
        <?php else: ?><span class="not-set">Not set</span><?php endif; ?>
      </td>
    </tr>
    <tr>
      <th>alsoKnownAs</th>
      <td>
        <?php
        $aka = json_decode($account['also_known_as'] ?? '[]', true) ?? [];
        if (empty($aka)): ?>
        <span class="not-set">None</span>
        <?php else: ?>
        <?php foreach ($aka as $uri): ?>
        <div class="aka-row">
          <span><?= h($uri) ?></span>
          <form method="POST" action="<?= h(admin_url('move/' . $account['id'] . '/remove_known_as')) ?>" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="remove_uri" value="<?= h($uri) ?>">
            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
          </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </td>
    </tr>
  </table>
</div>

<div class="grid-2col">

<!-- Move Followers Away (set movedTo) -->
<div class="card">
  <h2>Move Followers Away from This Bot</h2>
  <p class="move-description">
    Sends a Move activity to all followers, telling them to follow the new account.
    <strong>The new account must list this account's URI in its alsoKnownAs first.</strong>
  </p>
  <form method="POST" action="<?= h(admin_url('move/' . $account['id'] . '/move_away')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>New Account URI</label>
      <input type="url" name="new_account_uri" required placeholder="https://newserver.example/users/newbot"
             value="<?= h($_POST['new_account_uri'] ?? $account['moved_to'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary" data-confirm="Send Move activity to all followers? This will redirect them to the new account.">
      Send Move Activity
    </button>
  </form>
</div>

<!-- Receive Followers (set alsoKnownAs) -->
<div class="card">
  <h2>Receive Followers from Another Account</h2>

  <?php if (!empty($migrationWarnings)): ?>
  <div class="alert alert-error">
    <?php foreach ($migrationWarnings as $w): ?>
    <div>⚠ <?= h($w) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <ol class="step-list">
    <li>Ensure <strong>Require follower approval</strong> is <strong>OFF</strong> for this bot (<a href="<?= h(admin_url('bots/' . $account['id'] . '/edit')) ?>">Bot Settings</a>)</li>
    <li>Enter the old account URI below and click <em>Add to alsoKnownAs</em> — verifies the old actor is reachable</li>
    <li>Confirm the old URI appears in <strong>alsoKnownAs</strong> in the Current Status table above</li>
    <li><strong>On the old Mastodon instance</strong>, go to <strong>Settings → Account → Moving from a different account → Create an account alias</strong> and enter this bot's handle:<br>
      <code class="inline-code"><?= h('@' . $account['username'] . '@' . get_domain()) ?></code><br>
      <span class="move-step-danger">This step is required first — the old server must alias to this account before Move will work.</span></li>
    <li>Then on the old instance go to <strong>Settings → Account → Move Account</strong> and enter this bot's handle (same as above)</li>
    <li>The old server sends a Move activity to every follower; each follower's server will automatically send a Follow here, which will be auto-accepted</li>
  </ol>

  <form method="POST" action="<?= h(admin_url('move/' . $account['id'] . '/add_known_as')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>Old Account Handle or URI</label>
      <input type="text" name="old_account_uri" required placeholder="@user@mastodon.social">
      <small class="small-form-hint">Handle (@user@domain) or full actor URI</small>
    </div>
    <button type="submit" class="btn btn-primary">Add to alsoKnownAs</button>
  </form>
</div>

</div>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
