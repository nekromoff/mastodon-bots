<?php
$pageTitle = 'Social — @' . $account['username'];
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1>Social — @<?= h($account['username']) ?></h1>
<div class="section-nav">
  <a href="<?= h(admin_url('bots')) ?>" class="btn btn-secondary btn-sm">← Bots</a>
  <a href="<?= h(admin_url('bots/' . $account['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Edit</a>
  <a href="<?= h(admin_url('post/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">Post</a>
  <span class="nav-current">Social</span>
  <a href="<?= h(admin_url('move/' . $account['id'])) ?>" class="btn btn-secondary btn-sm">Move</a>
</div>

<?php if (isset($_GET['followed'])): ?><div class="alert alert-success">Follow sent.</div><?php endif; ?>
<?php if (isset($_GET['unfollowed'])): ?><div class="alert alert-success">Unfollowed.</div><?php endif; ?>
<?php if (isset($_GET['blocked'])): ?><div class="alert alert-success">Blocked.</div><?php endif; ?>
<?php if (isset($_GET['unblocked'])): ?><div class="alert alert-success">Unblocked.</div><?php endif; ?>
<?php if (isset($_GET['accepted'])): ?><div class="alert alert-success">Follow accepted.</div><?php endif; ?>
<?php if (isset($_GET['rejected'])): ?><div class="alert alert-success">Follow rejected.</div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<div class="grid-2col">

<!-- Follow -->
<div class="card">
  <h2>Follow Remote Account</h2>
  <form method="POST" action="<?= h(admin_url('social/' . $account['id'] . '/follow')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>Handle or Actor URI</label>
      <input type="text" name="target_uri" required placeholder="@user@instance.example.com">
    </div>
    <button type="submit" class="btn btn-primary">Follow</button>
  </form>
</div>

<!-- Block -->
<div class="card">
  <h2>Block Actor</h2>
  <form method="POST" action="<?= h(admin_url('social/' . $account['id'] . '/block')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-group">
      <label>Handle or Actor URI</label>
      <input type="text" name="target_uri" required placeholder="@user@instance.example.com">
    </div>
    <button type="submit" class="btn btn-danger">Block</button>
  </form>
</div>

</div>

<!-- Pending Follow Requests -->
<?php if (!empty($pendingFollowers)): ?>
<div class="card">
  <h2>Pending Follow Requests (<?= count($pendingFollowers) ?>)</h2>
  <table>
    <thead><tr><th>Actor URI</th><th>Received</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($pendingFollowers as $pf): ?>
    <tr>
      <td class="break-all text-sm"><a href="<?= h($pf['follower_uri']) ?>" target="_blank"><?= h($pf['follower_uri']) ?></a></td>
      <td class="text-sm text-muted"><?= h(date('M j, Y', strtotime($pf['created_at']))) ?></td>
      <td>
        <form method="POST" action="<?= h(admin_url('social/' . $account['id'] . '/accept_follower')) ?>" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="follower_uri" value="<?= h($pf['follower_uri']) ?>">
          <button type="submit" class="btn btn-primary btn-sm">Accept</button>
        </form>
        <form method="POST" action="<?= h(admin_url('social/' . $account['id'] . '/reject_follower')) ?>" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="follower_uri" value="<?= h($pf['follower_uri']) ?>">
          <button type="submit" class="btn btn-danger btn-sm">Reject</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Followers List -->
<div class="card">
  <h2>Followers (<?= count($followers) ?>)</h2>
  <?php if (empty($followers)): ?>
  <p class="text-muted">No followers yet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Actor URI</th><th>Since</th></tr></thead>
    <tbody>
    <?php foreach ($followers as $f): ?>
    <tr>
      <td class="break-all text-sm"><a href="<?= h($f['follower_uri']) ?>" target="_blank"><?= h($f['follower_uri']) ?></a></td>
      <td class="text-sm text-muted"><?= h(date('M j, Y', strtotime($f['created_at']))) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Following List -->
<div class="card">
  <h2>Following (<?= count($following) ?>)</h2>
  <?php if (empty($following)): ?>
  <p class="text-muted">Not following anyone yet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Actor URI</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($following as $f): ?>
    <tr>
      <td class="break-all text-sm"><a href="<?= h($f['following_uri']) ?>" target="_blank"><?= h($f['following_uri']) ?></a></td>
      <td><?= $f['accepted'] ? '<span class="badge badge-success">Accepted</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
      <td>
        <form method="POST" action="<?= h(admin_url('social/' . $account['id'] . '/unfollow')) ?>" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="target_uri" value="<?= h($f['following_uri']) ?>">
          <button type="submit" class="btn btn-secondary btn-sm" data-confirm="Unfollow?">Unfollow</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Blocks List -->
<div class="card">
  <h2>Blocked (<?= count($blocks) ?>)</h2>
  <?php if (empty($blocks)): ?>
  <p class="text-muted">No blocked accounts.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Actor URI</th><th>Blocked At</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($blocks as $b): ?>
    <tr>
      <td class="break-all text-sm"><?= h($b['target_uri']) ?></td>
      <td class="text-sm text-muted"><?= h(date('M j, Y', strtotime($b['created_at']))) ?></td>
      <td>
        <form method="POST" action="<?= h(admin_url('social/' . $account['id'] . '/unblock')) ?>" class="inline-form">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="target_uri" value="<?= h($b['target_uri']) ?>">
          <button type="submit" class="btn btn-secondary btn-sm">Unblock</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
