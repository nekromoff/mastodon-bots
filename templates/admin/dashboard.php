<?php
$pageTitle = 'Bots';
require BASE_PATH . '/templates/admin/layout.php';

$accounts = get_all_accounts();
$totalPosts = (int) (db_get('SELECT COUNT(*) as c FROM posts WHERE deleted_at IS NULL')['c'] ?? 0);
$totalFollowers = (int) (db_get('SELECT COUNT(*) as c FROM followers WHERE accepted = 1')['c'] ?? 0);
$totalLog = (int) (db_get('SELECT COUNT(*) as c FROM activities_log')['c'] ?? 0);
$failedLog = (int) (db_get("SELECT COUNT(*) as c FROM activities_log WHERE status = 'failed'")['c'] ?? 0);
?>

<h1>Bots</h1>

<div class="stats-grid">
  <div class="card stat-card">
    <div class="stat-value"><?= count($accounts) ?></div>
    <div class="stat-label">Bots</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value"><?= $totalPosts ?></div>
    <div class="stat-label">Total Posts</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value"><?= $totalFollowers ?></div>
    <div class="stat-label">Total Followers</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value <?= $failedLog > 0 ? 'stat-value--danger' : 'stat-value--success' ?>"><?= $failedLog ?></div>
    <div class="stat-label">Failed Deliveries</div>
  </div>
  <div class="card stat-card">
    <div class="stat-value"><?= $totalLog ?></div>
    <div class="stat-label">Log Entries</div>
  </div>
</div>

<?php if (isset($_GET['created'])): ?><div class="alert alert-success">Bot created successfully.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Bot deleted.</div><?php endif; ?>

<?php if (empty($accounts)): ?>
<div class="card">
  No bots yet. <a href="<?= h(admin_url('bots/create')) ?>">Create your first bot →</a>
</div>
<?php else: ?>
<div class="card table-no-padding">
  <table class="bots-table">
    <thead>
      <tr>
        <th>Username</th>
        <th class="col-detail">Display Name</th>
        <th class="col-detail">Posts</th>
        <th class="col-detail">Followers</th>
        <th class="col-detail">Discoverable</th>
        <th class="col-detail">Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($accounts as $acc):
    $pc = (int) (db_get('SELECT COUNT(id) as c FROM posts WHERE account_id = ? AND deleted_at IS NULL', [$acc['id']])['c'] ?? 0);
    $followers = db_get('SELECT COUNT(id) as total, COUNT(CASE WHEN created_at > datetime("now", "-3 days") THEN 1 END) as diff FROM followers WHERE account_id = ? AND accepted = 1', [$acc['id']]);
    $total = (int) ($followers['total'] ?? 0);
    $diff = (int) ($followers['diff'] ?? 0);
    $hchar;
?>
    <tr>
      <td>
        <a href="<?= h(profile_url($acc['username'])) ?>" target="_blank">@<?= h($acc['username']) ?></a><?= $acc['manually_approves_followers'] ? ' <span title="Follower approval is ON" class="approval-icon">⚠️</span>' : '' ?>
        <div class="bot-meta">
          <?php if ($acc['display_name']): ?><span class="bot-meta-item"><?= h($acc['display_name']) ?></span><?php endif; ?>
          <span class="bot-meta-item"><?= $pc ?> posts</span>
          <span class="bot-meta-item"><?= $total ?> followers <?php echo($diff > 0) ? '(<span class="stat-value--success"><strong>+' . $diff . '</strong><span>)' : (($diff < 0) ? '(<span class="stat-value--danger"><strong>-' . $diff . '</strong></span>)' : ''); ?></span>
          <span class="bot-meta-item"><?= $acc['discoverable'] ? '<span class="badge badge-success">Discoverable</span>' : '<span class="badge badge-secondary">Not discoverable</span>' ?></span>
          <span class="bot-meta-item text-muted"><?= h(date('M j, Y', strtotime($acc['created_at']))) ?></span>
        </div>
      </td>
      <td class="col-detail"><?= h($acc['display_name'] ?: '—') ?></td>
      <td class="col-detail"><?= $pc ?></td>
      <td class="col-detail"><?= $total ?> <?php echo($diff > 0) ? '(<span class="stat-value--success"><strong>+' . $diff . '</strong><span>)' : (($diff < 0) ? '(<span class="stat-value--danger"><strong>-' . $diff . '</strong></span>)' : ''); ?></td>
      <td class="col-detail"><?= $acc['discoverable'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
      <td class="col-detail text-sm text-muted"><?= h(date('M j, Y', strtotime($acc['created_at']))) ?></td>
      <td>
        <div class="flex-actions">
          <a href="<?= h(admin_url('bots/' . $acc['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Edit</a>
          <a href="<?= h(admin_url('post/' . $acc['id'])) ?>" class="btn btn-secondary btn-sm">Post</a>
          <a href="<?= h(admin_url('social/' . $acc['id'])) ?>" class="btn btn-secondary btn-sm">Social</a>
          <a href="<?= h(admin_url('move/' . $acc['id'])) ?>" class="btn btn-tertiary btn-sm">Move</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="section-actions">
  <a href="<?= h(admin_url('bots/create')) ?>" class="btn btn-primary">+ Create New Bot</a>
</div>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
    $pc = (int) (db_get('SELECT COUNT(id) as c FROM posts WHERE account_id = ? AND deleted_at IS NULL', [$acc['id']])['c'] ?? 0);
