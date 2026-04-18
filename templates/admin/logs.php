<?php
$pageTitle = 'Activity Logs';
$extraMainClass = 'logs-page';
require BASE_PATH . '/templates/admin/layout.php';
?>

<h1>Activity Logs (<?= count($logs) ?>)</h1>

<?php if (isset($_GET['cleared'])): ?><div class="alert alert-success">Cleared <?= (int)$_GET['cleared'] ?> log entries.</div><?php endif; ?>

<div class="logs-filter-bar">
  <form method="POST" action="<?= h(admin_url('logs' . ($botId ? '/' . $botId : '') . '/clear')) ?>"
        data-confirm="Clear all logs?">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button type="submit" class="btn btn-danger">Clear All Logs</button>
  </form>

  <div class="flex-row" id="logs-filters" data-base="<?= h(admin_url('logs')) ?>">
    <select class="btn btn-secondary logs-filter-select" id="logs-direction-filter">
      <option value="" <?= empty($direction) ? 'selected' : '' ?>>All directions</option>
      <option value="in" <?= $direction === 'in' ? 'selected' : '' ?>>Incoming</option>
      <option value="out" <?= $direction === 'out' ? 'selected' : '' ?>>Outgoing</option>
    </select>
    <select class="btn btn-secondary logs-filter-select" id="logs-bot-filter">
      <option value="">All bots</option>
      <?php foreach ($accounts as $acc): ?>
      <option value="<?= $acc['id'] ?>" <?= $botId == $acc['id'] ? 'selected' : '' ?>>@<?= h($acc['username']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="btn btn-secondary logs-filter-select" id="logs-event-filter">
      <option value="" <?= empty($eventType) ? 'selected' : '' ?>>All event types</option>
      <?php foreach (['Follow', 'Accept', 'Reject', 'Undo', 'Create', 'Update', 'Delete', 'Announce', 'Like', 'Block', 'Move'] as $et): ?>
      <option value="<?= $et ?>" <?= $eventType === $et ? 'selected' : '' ?>><?= $et ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php if (empty($logs)): ?>
<div class="card no-logs">No log entries.</div>
<?php else: ?>
<div class="card table-no-padding">
  <div class="table-scroll">
  <table>
    <thead class="logs-thead">
      <tr>
        <th>ID</th>
        <th>Bot</th>
        <th>Dir</th>
        <th>Type</th>
        <th>Summary</th>
        <th>Status</th>
        <th>Target</th>
        <th>Time</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log):
    // Build human-readable summary from activity JSON
    $aj = json_decode($log['activity_json'] ?? '{}', true) ?? [];
    $type = $log['activity_type'] ?? '';
    $actor = $aj['actor'] ?? '';
    $actorShort = is_string($actor) ? (parse_url($actor, PHP_URL_HOST) . '/@' . ltrim(parse_url($actor, PHP_URL_PATH), '/users/')) : '';
    // Simplify actor to just @user@host when possible
    if (is_string($actor) && preg_match('#/users/([^/]+)$#', $actor, $m)) {
        $actorShort = '@' . $m[1] . '@' . parse_url($actor, PHP_URL_HOST);
    }
    $obj = $aj['object'] ?? '';
    $objId = is_array($obj) ? ($obj['id'] ?? '') : (is_string($obj) ? $obj : '');
    $objType = is_array($obj) ? ($obj['type'] ?? '') : '';
    $summary = match ($type) {
        'Follow' => $actorShort ? "follow from {$actorShort}" : 'follow',
        'Accept' => $actorShort ? "accepted by {$actorShort}" : 'accept',
        'Reject' => $actorShort ? "rejected by {$actorShort}" : 'reject',
        'Undo' => ($objType ?: (is_array($obj) ? '' : '')) ?
        'undo ' . strtolower($objType ?: 'action') . ($actorShort ? " by {$actorShort}" : '') :
        'undo' . ($actorShort ? " by {$actorShort}" : ''),
        'Create' => 'post: ' . mb_substr(html_entity_decode(strip_tags(is_array($obj) ? ($obj['content'] ?? $obj['summary'] ?? '') : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 120) . ($objId ? ' [<a href="' . $objId . '">' . $objId . '</a>]' : ''),
        'Update' => 'update: ' . mb_substr(html_entity_decode(strip_tags(is_array($obj) ? ($obj['content'] ?? $obj['summary'] ?? $objId) : $objId), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 100) . ($objId ? ' [<a href="' . $objId . '">' . $objId . '</a>]' : ''),
        'Delete' => 'delete: <a href="' . $objId . '">' . $objId . '</a>',
        'Announce' => 'boost: <a href="' . $objId . '">' . $objId . '</a>',
        'Like' => 'like: <a href="' . $objId . '">' . $objId . '</a>',
        'Block' => 'block: <a href="' . $objId . '">' . $objId . '</a>',
        'Move' => 'move → ' . ($aj['target'] ?? ''),
        default => '',
    };
?>
    <tr class="log-row">
      <td class="log-id-cell" data-label="ID"><?= (int)$log['id'] ?></td>
      <td class="log-bot-cell" data-label="Bot"><?= $log['username'] ? '@' . h($log['username']) : '—' ?></td>
      <td data-label="Dir">
        <?php if ($log['direction'] === 'in'): ?>
        <span class="badge badge-secondary">↙ in</span>
        <?php else: ?>
        <span class="badge badge-secondary">↗ out</span>
        <?php endif; ?>
      </td>
      <td class="log-type-cell" data-label="Type"><?= h($type) ?></td>
      <td class="log-summary-cell" data-label="Summary"><?= $summary ?: '—' ?></td>
      <td data-label="Status">
        <?php if ($log['status'] === 'delivered' || $log['status'] === 'received'): ?>
        <span class="badge badge-success"><?= h($log['status']) ?></span>
        <?php elseif ($log['status'] === 'failed'): ?>
        <span class="badge badge-danger">failed</span>
        <?php else: ?>
        <span class="badge badge-warning"><?= h($log['status']) ?></span>
        <?php endif; ?>
      </td>
      <td class="log-target-cell" data-label="Target"><?= h($log['target_inbox'] ?: $log['remote_actor'] ?: '—') ?></td>
      <td class="log-time-cell" data-label="Time"><?= h(date('M j H:i', strtotime($log['created_at']))) ?></td>
      <td data-label="Details">
        <?php if (!empty($log['error'])): ?>
        <button type="button" class="btn btn-secondary btn-sm btn-error" data-toggle-next>⚠ error</button>
        <pre class="error-detail-box"><?= h($log['error']) ?></pre>
        <?php else: ?>
        <button type="button" class="btn btn-secondary btn-sm" data-toggle-next>JSON</button>
        <pre class="json-detail-box"><?= h(json_encode(json_decode($log['activity_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<?php require BASE_PATH . '/templates/admin/layout_end.php'; ?>
