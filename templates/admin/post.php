<?php
$pageTitle = 'Posts — @' . $account['username'];
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1>Posts for @<?= h($account['username']) ?></h1>

<div class="section-nav">
  <a href="<?= h(admin_url('bots')) ?>" class="btn btn-secondary btn-sm">← Bots</a>
  <a href="<?= h(admin_url('bots/' . $account['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Edit</a>
  <span class="nav-current">Post</span>
  <a href="<?= h(admin_url('social/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">Social</a>
  <a href="<?= h(admin_url('move/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">Move</a>
</div>

<?php if (isset($_GET['created'])): ?><div class="alert alert-success">Post published!</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Post updated.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Post deleted.</div><?php endif; ?>
<?php if (!empty($postError)): ?><div class="alert alert-error"><?= h($postError) ?></div><?php endif; ?>

<?php if ($editPost): ?>
<!-- Edit Post Form -->
<div class="card">
  <h2>Edit Post</h2>
  <form method="POST" action="<?= h(admin_url('post/' . $account['id'] . '/' . $editPost['id'] . '/edit')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>Content Warning / Spoiler (optional)</label>
      <input type="text" name="content_warning" value="<?= h($editPost['content_warning']) ?>"
             placeholder="Leave blank for no CW">
    </div>
    <div class="form-group">
      <label>Post Content</label>
      <textarea name="content" rows="6" required><?= h($editPost['content_raw']) ?></textarea>
      <small class="form-hint">URLs, #hashtags, and @mentions are autolinked.</small>
    </div>
    <button type="submit" class="btn btn-primary">Save Edit</button>
    <a href="<?= h(admin_url('post/' . $account['id'])) ?>" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<?php else: ?>
<!-- Compose Form -->
<div class="card">
  <h2>Compose Post</h2>
  <form method="POST" action="<?= h(admin_url('post/' . $account['id'] . '/create')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="form-group">
      <label>Content Warning (optional — adds ⚠️ spoiler)</label>
      <input type="text" name="content_warning" placeholder="Leave blank for none">
    </div>

    <div class="form-group">
      <label>Post Content</label>
      <textarea name="content" rows="6" placeholder="What's happening? URLs, #hashtags and @mentions are autolinked."></textarea>
    </div>

    <div class="compose-grid">
      <div class="form-group">
        <label>Visibility</label>
        <select name="visibility">
          <option value="public">🌐 Public</option>
          <option value="unlisted">🔓 Unlisted</option>
          <option value="private">🔒 Followers only</option>
          <option value="direct">✉️ Direct message</option>
        </select>
      </div>
      <div class="form-group">
        <label>Language</label>
        <input type="text" name="language" value="en" maxlength="5" placeholder="en">
      </div>
    </div>

    <div class="form-group">
      <label>Reply to (paste post URI, optional)</label>
      <input type="url" name="in_reply_to" placeholder="https://...">
    </div>

    <div class="form-group">
      <label>Quote Post URI (optional)</label>
      <input type="url" name="quote_url" placeholder="https://...">
    </div>

    <?php if (!empty($pendingMedia)): ?>
    <div class="form-group">
      <label>Attach uploaded media</label>
      <div class="media-picker-grid">
        <?php foreach ($pendingMedia as $m): ?>
        <label class="media-picker-item">
          <input type="checkbox" name="media_ids[]" value="<?= $m['id'] ?>" style="width:auto">
          <?php if (str_starts_with($m['mime_type'], 'image/')): ?>
          <img src="<?= h($m['file_url']) ?>" class="media-thumb">
          <?php else: ?>
          <div class="media-placeholder">
            <?= str_starts_with($m['mime_type'], 'video/') ? '🎦' : '🔊' ?>
          </div>
          <?php endif; ?>
          <div class="media-label"><?= h(mb_substr($m['alt_text'] ?: $m['mime_type'], 0, 20)) ?></div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="flex-row">
      <button type="submit" class="btn btn-primary">Publish Post</button>
      <a href="<?= h(admin_url('post/' . $account['id'])) ?>#upload" class="btn btn-secondary">Upload Media</a>
      <?php if (!empty($pendingMedia)): ?>
      <form method="POST" action="<?= h(admin_url('post/' . $account['id'] . '/clear_draft')) ?>"
            data-confirm="Delete all uploaded draft media?" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="btn btn-danger">Clear Draft</button>
      </form>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Media Upload -->
<div class="card" id="upload">
  <h2>Upload Media</h2>
  <?php if (isset($_GET['upload_error'])): ?><div class="alert alert-error"><?= h($_GET['upload_error']) ?></div><?php endif; ?>
  <form method="POST" action="<?= h(site_url('media/upload')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="bot_id" value="<?= $account['id'] ?>">
    <input type="hidden" name="redirect_to" value="<?= h(admin_url('post/' . $account['id']) . '#upload') ?>">
    <div class="form-group">
      <label>File (image, video, audio)</label>
      <input type="file" name="file" accept="image/*,video/mp4,video/webm,audio/mpeg,audio/ogg,audio/wav" required>
    </div>
    <div class="form-group">
      <label>Alt text (for accessibility)</label>
      <input type="text" name="alt_text" placeholder="Describe the media…">
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
</div>
<?php endif; ?>

<!-- Post List -->
<h2 class="posts-heading">Recent Posts</h2>
<?php if (empty($botPosts)): ?>
<div class="card no-posts">No posts yet.</div>
<?php else: ?>
<?php foreach ($botPosts as $p): ?>
<div class="card">
  <div class="flex-between">
    <div class="post-list-body">
      <?php if (!empty($p['content_warning'])): ?>
      <div class="post-cw-label">⚠️ <?= h($p['content_warning']) ?></div>
      <?php endif; ?>
      <div class="post-content-text"><?= $p['content_html'] ?></div>
      <?php if (!empty($p['attachments'])): ?>
      <div class="post-thumbs">
        <?php foreach ($p['attachments'] as $att): ?>
          <?php if (str_starts_with($att['mime_type'], 'image/')): ?>
          <a href="<?= h($att['file_url']) ?>"><img src="<?= h($att['file_url']) ?>" alt="<?= h($att['alt_text']) ?>" class="post-thumb-img"></a>
          <?php else: ?>
          <span class="post-thumb-label"><?= h($att['mime_type']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="post-meta-line">
        <?= h(date('M j, Y g:i A', strtotime($p['published_at']))) ?>
        · <span class="post-visibility"><?= h($p['visibility']) ?></span>
        <?php if (!empty($p['updated_at'])): ?> · edited<?php endif; ?>
      </div>
    </div>
    <div class="flex-actions">
      <a href="<?= h(profile_url($account['username']) . '/' . rawurlencode(basename($p['activity_id']))) ?>" target="_blank" class="btn btn-secondary btn-sm">View</a>
      <a href="<?= h(admin_url('post/' . $account['id'] . '/' . $p['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Edit</a>
      <form method="POST" action="<?= h(admin_url('post/' . $account['id'] . '/' . $p['id'] . '/delete')) ?>"
            data-confirm="Delete this post?" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
