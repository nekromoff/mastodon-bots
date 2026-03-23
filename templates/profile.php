<?php
$pageTitle  = ($account['display_name'] ?: $account['username']) . ' (@' . $account['username'] . '@' . get_domain() . ')';
$avatar     = !empty($account['avatar_path'])
    ? base_url() . '/uploads/avatars/' . $account['avatar_path']
    : null;
$header     = !empty($account['header_path'])
    ? base_url() . '/uploads/headers/' . $account['header_path']
    : null;
$robotsMeta = $account['noindex'] ? 'noindex,nofollow' : 'index,follow';

$headTags  = '<link rel="canonical" href="' . h(profile_url($account['username'])) . '">' . "\n";
$headTags .= '<link rel="alternate" type="application/activity+json" href="' . h(actor_url($account['username'])) . '">' . "\n";
if (!empty($account['fediverse_creator'])) {
    $headTags .= '<meta name="fediverse:creator" content="' . h($account['fediverse_creator']) . '">' . "\n";
}

require BASE_PATH . '/templates/layout.php';
?>

<?php if ($header): ?>
<a href="<?= h($header) ?>" target="_blank" rel="noopener" class="profile-header-link">
  <div class="profile-header-image" style="background-image:url('<?= h($header) ?>')"></div>
</a>
<?php endif; ?>

<div class="card <?= $header ? 'profile-card--with-header' : 'profile-card--no-header' ?>">
  <div class="profile-info">
    <?php if ($avatar): ?>
    <a href="<?= h($avatar) ?>" target="_blank" rel="noopener">
      <img src="<?= h($avatar) ?>" alt="<?= h($account['display_name'] ?: $account['username']) ?>"
           class="profile-avatar">
    </a>
    <?php else: ?>
    <div class="profile-avatar-placeholder">
      <?= strtoupper(substr($account['username'], 0, 1)) ?>
    </div>
    <?php endif; ?>

    <div>
      <h1 class="profile-name"><?= h($account['display_name'] ?: $account['username']) ?></h1>
      <p class="profile-username">@<?= h($account['username']) ?>@<?= h(get_domain()) ?></p>
    </div>

    <div class="profile-counts">
      <?php
        $followerCount = (int)(db_get("SELECT COUNT(*) as c FROM followers WHERE account_id = ? AND accepted = 1", [$account['id']])['c'] ?? 0);
        $followingCount = (int)(db_get("SELECT COUNT(*) as c FROM following WHERE account_id = ?", [$account['id']])['c'] ?? 0);
        $postCount = (int)(db_get("SELECT COUNT(*) as c FROM posts WHERE account_id = ? AND deleted_at IS NULL", [$account['id']])['c'] ?? 0);
      ?>
      <div><strong><?= $postCount ?></strong> posts</div>
      <div><strong><?= $followerCount ?></strong> followers · <strong><?= $followingCount ?></strong> following</div>
    </div>
  </div>

  <?php if (!empty($account['bio'])): ?>
  <?php
    require_once LIB_PATH . '/autolink.php';
    $bioHtml = autolink_content($account['bio'], get_domain())['html'];
  ?>
  <div class="profile-bio"><?= $bioHtml ?></div>
  <?php endif; ?>

  <?php
    $profileFields = json_decode($account['profile_fields'] ?? '[]', true) ?: [];
    if (!empty($profileFields)):
  ?>
  <table class="profile-fields">
    <?php foreach ($profileFields as $field): ?>
    <tr>
      <td class="field-label"><?= h($field['name']) ?></td>
      <td class="field-value"><?= filter_var($field['value'], FILTER_VALIDATE_URL)
        ? '<a href="' . h($field['value']) . '" rel="nofollow noopener noreferrer" target="_blank">' . h($field['value']) . '</a>'
        : h($field['value']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php
    $featuredTags = json_decode($account['featured_hashtags'] ?? '[]', true) ?: [];
    if (!empty($featuredTags)):
  ?>
  <div class="profile-hashtags">
    <?php foreach ($featuredTags as $tag): ?>
    <a href="<?= h(base_url() . '/tags/' . urlencode($tag)) ?>" class="tag">#<?= h($tag) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($account['moved_to'])): ?>
  <div class="alert alert-error profile-moved">
    ⚠️ This account has moved to <a href="<?= h($account['moved_to']) ?>"><?= h($account['moved_to']) ?></a>
  </div>
  <?php endif; ?>
</div>

<h2 class="profile-posts-heading">Posts</h2>

<?php if (empty($posts)): ?>
<div class="card empty-state">No posts yet.</div>
<?php else: ?>
  <?php foreach ($posts as $post):
    $post['username']     = $account['username'];
    $post['display_name'] = $account['display_name'];
    $post['avatar_path']  = $account['avatar_path'];
    require BASE_PATH . '/templates/post_card.php';
  endforeach; ?>
<?php endif; ?>

<?php require BASE_PATH . '/templates/layout_end.php'; ?>
