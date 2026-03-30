<?php
$pageTitle = 'Settings';
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1>Server Settings</h1>

<?php if (!empty($success)): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<div class="card">
  <form method="POST" action="<?= h(admin_url('settings')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="form-group">
      <label>Domain (no https:// — e.g. bots.example.com)</label>
      <input type="text" name="domain" required value="<?= h($settings['domain']) ?>">
      <small class="form-hint">Changing this will break existing actor URIs and followers.</small>
    </div>

    <div class="form-group">
      <label>Max log rows (auto-clear oldest when exceeded)</label>
      <input type="number" name="max_log_rows" value="<?= h($settings['max_log_rows']) ?>" min="100" step="100">
    </div>

    <div class="form-group">
      <label>Log retention (days — delete entries older than this, 0 = keep forever)</label>
      <input type="number" name="log_retention_days" value="<?= h($settings['log_retention_days']) ?>" min="0" step="1">
    </div>

    <div class="form-group">
      <label>Max media upload size (MB)</label>
      <input type="number" name="media_max_mb" value="<?= h((string)$settings['media_max_mb']) ?>" min="1" max="500">
    </div>

    <div class="form-group">
      <label>Webhook URL (leave blank to disable)</label>
      <input type="url" name="webhook_url" value="<?= h($settings['webhook_url']) ?>" placeholder="https://example.com/webhook">
      <small class="form-hint">Receives a POST request (JSON) when someone replies to a bot post.</small>
    </div>

    <div class="form-group">
      <label>Relays (one inbox URL per line)</label>
      <textarea name="relays" rows="4" placeholder="https://relay.toot.io/inbox"><?= h(implode("\n", $relayUrls)) ?></textarea>
      <small class="form-hint">ActivityPub relays for wider federation. Public posts will be sent to accepted relays.</small>
<?php if (!empty($relayStatus)): ?>
      <div class="relay-status" style="margin-top:0.5rem;">
<?php foreach ($relayStatus as $url => $st): ?>
        <div style="font-size:0.85em;margin-bottom:0.25rem;">
          <code><?= h($url) ?></code> —
<?php if ($st['accepted'] === $st['total']): ?>
          <span class="badge badge-success"><?= $st['accepted'] ?>/<?= $totalBots ?> bots accepted</span>
<?php elseif ($st['rejected'] > 0): ?>
          <span class="badge badge-danger"><?= $st['rejected'] ?> rejected</span>
<?php else: ?>
          <span class="badge badge-secondary"><?= $st['pending'] ?> pending</span>
<?php endif; ?>
        </div>
<?php endforeach; ?>
      </div>
<?php endif; ?>
    </div>

    <hr class="hr-divider">
    <h2>Change Admin Password</h2>
    <div class="form-group">
      <label>New Admin Password (leave blank to keep current)</label>
      <input type="password" name="admin_password" autocomplete="new-password" minlength="8">
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="admin_password2" autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>
</div>

<div class="card">
  <h2>API Usage</h2>
  <p class="move-description">
    Each bot can be controlled programmatically via the REST API using HTTP Basic auth (bot username + password).
  </p>
  <pre class="pre-block">
# Create a post
curl -u botname:botpassword \
     -X POST <?= h(base_url()) ?>/api/post \
     -H "Content-Type: application/json" \
     -d '{"content":"Hello Fediverse! #test","visibility":"public"}'

# Upload media
curl -u botname:botpassword \
     -X POST <?= h(base_url()) ?>/api/media \
     -F "file=@photo.jpg" \
     -F "alt_text=A photo"

# Post with media (use ID from upload response)
curl -u botname:botpassword \
     -X POST <?= h(base_url()) ?>/api/post \
     -H "Content-Type: application/json" \
     -d '{"content":"Check this out!","media_ids":[1]}'

# Edit a post
curl -u botname:botpassword \
     -X PUT "<?= h(base_url()) ?>/api/post?post_id=1" \
     -H "Content-Type: application/json" \
     -d '{"content":"Updated content"}'

# Delete a post
curl -u botname:botpassword \
     -X DELETE "<?= h(base_url()) ?>/api/post?post_id=1"

# Follow a remote account
curl -u botname:botpassword \
     -X POST <?= h(base_url()) ?>/api/follow \
     -H "Content-Type: application/json" \
     -d '{"actor_uri":"https://mastodon.social/users/alice"}'
  </pre>
</div>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
