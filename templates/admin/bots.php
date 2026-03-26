<?php
$showCreate = ($botAction ?? '') === 'create';
$pageTitle  = $editBot ? 'Edit — @' . $editBot['username'] : 'Bots';
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1><?= $editBot ? 'Edit — @' . h($editBot['username']) : 'Bots' ?></h1>

<?php if (isset($_GET['created'])): ?><div class="alert alert-success">Bot created successfully.</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Bot updated.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Bot deleted.</div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
<?php if (!empty($editError)): ?><div class="alert alert-error"><?= h($editError) ?></div><?php endif; ?>

<?php if ($editBot): ?>
<div class="section-nav">
  <a href="<?= h(admin_url()) ?>" class="btn btn-secondary btn-sm">← Bots</a>
  <span class="nav-current">Edit</span>
  <a href="<?= h(admin_url('post/' . $editBot['id'])) ?>" class="btn btn-secondary btn-sm">Post</a>
  <a href="<?= h(admin_url('social/' . $editBot['id'])) ?>" class="btn btn-secondary btn-sm">Social</a>
  <a href="<?= h(admin_url('move/' . $editBot['id'])) ?>" class="btn btn-secondary btn-sm">Move</a>
</div>
<!-- Edit Bot Form -->
<div class="card">
  <form method="POST" action="<?= h(admin_url('bots/' . $editBot['id'] . '/edit')) ?>"
        enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>Display Name</label>
      <input type="text" name="display_name" value="<?= h($editBot['display_name']) ?>">
    </div>
    <div class="form-group">
      <label>Bio (HTML is escaped — use plain text)</label>
      <textarea name="bio"><?= h($editBot['bio']) ?></textarea>
    </div>
    <div class="form-group">
      <label>Avatar image (JPG/PNG/GIF/WebP)</label>
      <?php if (!empty($editBot['avatar_path'])): ?>
      <img src="<?= h(site_url('uploads/avatars/' . $editBot['avatar_path'])) ?>" class="bot-avatar-preview">
      <?php endif; ?>
      <input type="file" name="avatar" accept="image/*">
    </div>
    <div class="form-group">
      <label>Header image (JPG/PNG/GIF/WebP)</label>
      <?php if (!empty($editBot['header_path'])): ?>
      <img src="<?= h(site_url('uploads/headers/' . $editBot['header_path'])) ?>" class="bot-header-preview">
      <?php endif; ?>
      <input type="file" name="header" accept="image/*">
    </div>
    <div class="form-group">
      <label>Profile Fields (up to 4 — shown on Fediverse profile)</label>
      <?php
        $fields = json_decode($editBot['profile_fields'] ?? '[]', true) ?: [];
        for ($i = 0; $i < 4; $i++):
          $fn = $fields[$i]['name']  ?? '';
          $fv = $fields[$i]['value'] ?? '';
      ?>
      <div class="profile-field-row">
        <input type="text" name="field_name[]"  placeholder="Label" value="<?= h($fn) ?>" class="profile-field-key">
        <input type="text" name="field_value[]" placeholder="Content (URL or text)" value="<?= h($fv) ?>" class="profile-field-val">
      </div>
      <?php endfor; ?>
    </div>
    <div class="form-group">
      <label>Featured Hashtags (up to 10, # optional, space/comma/newline separated)</label>
      <?php $featuredTags = implode(' ', json_decode($editBot['featured_hashtags'] ?? '[]', true) ?: []); ?>
      <textarea name="featured_hashtags" rows="3" placeholder="#technology #php #activitypub"><?= h($featuredTags) ?></textarea>
    </div>
    <div class="form-group">
      <label>Fediverse Creator (<code>fediverse:creator</code> meta tag — your personal Fediverse handle, e.g. <code>@you@mastodon.social</code>)</label>
      <input type="text" name="fediverse_creator" value="<?= h($editBot['fediverse_creator'] ?? '') ?>" placeholder="@you@mastodon.social">
    </div>
    <div class="form-group">
      <label>New Bot Password (leave blank to keep current)</label>
      <input type="password" name="bot_password" autocomplete="new-password" minlength="6">
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="discoverable" value="1" <?= $editBot['discoverable'] ? 'checked' : '' ?>>
        Discoverable on Fediverse</label>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="indexable" value="1" <?= $editBot['indexable'] ? 'checked' : '' ?>>
        Feature profile and posts in discovery algorithms (Mastodon search &amp; explore)</label>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="searchengine_index" value="1" <?= !$editBot['noindex'] ? 'checked' : '' ?>>
        Include profile page in search engines</label>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="manually_approves_followers" value="1" <?= $editBot['manually_approves_followers'] ? 'checked' : '' ?>>
        Manually approve followers</label>
      <?php if ($editBot['manually_approves_followers']): ?>
      <div class="alert alert-error approval-warning">⚠️ Follower approval is ON — followers will not be automatically accepted. Disable this for bots that should accept follows automatically.</div>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="<?= h(admin_url()) ?>" class="btn btn-secondary">Cancel</a>
  </form>

  <hr class="hr-divider">
  <h2 class="danger-zone-heading">Danger Zone</h2>
  <form method="POST" action="<?= h(admin_url('bots/' . $editBot['id'] . '/delete')) ?>"
        onsubmit="return confirm('Delete this bot and all its posts? This cannot be undone.')">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button type="submit" class="btn btn-danger">Delete Bot</button>
  </form>
</div>

<?php elseif ($showCreate): ?>
<!-- Create Bot Form -->
<div class="card">
  <h2>Create New Bot</h2>
  <form method="POST" action="<?= h(admin_url('bots/create')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>Username (letters, numbers, _ -)</label>
      <input type="text" name="username" required pattern="[a-zA-Z0-9_\-]+" minlength="2"
             value="<?= h($_POST['username'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Display Name</label>
      <input type="text" name="display_name" value="<?= h($_POST['display_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Bio</label>
      <textarea name="bio"><?= h($_POST['bio'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Bot Password (for API auth)</label>
      <input type="text" name="bot_password" id="bot_password" required minlength="6" autocomplete="new-password"
             value="<?= h($_POST['bot_password'] ?? (function(){ $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+[]{}|;:,.<>?'; $s=''; for($i=0;$i<62;$i++) $s.=$chars[random_int(0,strlen($chars)-1)]; return $s; })()) ?>">
      <small class="form-hint"><a href="#" onclick="(function(){var c='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&amp;*()-_=+[]{}|;:,&lt;&gt;?',a=new Uint8Array(62),s='';crypto.getRandomValues(a);a.forEach(function(b){s+=c[b%c.length]});document.getElementById('bot_password').value=s})();return false">Regenerate</a></small>
    </div>
    <button type="submit" class="btn btn-primary">Create Bot</button>
    <a href="<?= h(admin_url()) ?>" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<?php endif; ?>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
