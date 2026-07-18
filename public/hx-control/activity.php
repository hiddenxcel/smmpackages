<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$rows = ActivityLog::recent(200);

$pageTitle = 'Activity Log';
$activeNav = 'activity';
require __DIR__ . '/_layout.php';
?>

<div class="card" style="padding:0;overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:720px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <?php foreach (['When','Actor','Action','Details','IP'] as $h): ?>
        <th style="padding:12px 16px;font-size:.78rem;color:var(--text-muted)"><?= $h ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="5" style="padding:30px;text-align:center;color:var(--text-muted)">No activity yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:11px 16px;color:var(--text-muted);font-size:.82rem;white-space:nowrap"><?= date('M j, H:i', strtotime($r['created_at'])) ?></td>
        <td style="padding:11px 16px"><span style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($r['actor_type']) ?></span><?= $r['actor_id'] ? ' #' . (int) $r['actor_id'] : '' ?></td>
        <td style="padding:11px 16px;font-weight:600;font-size:.88rem"><?= htmlspecialchars($r['action']) ?></td>
        <td style="padding:11px 16px;font-family:monospace;font-size:.78rem;color:var(--text-muted);max-width:280px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($r['details'] ?? '') ?></td>
        <td style="padding:11px 16px;color:var(--text-muted);font-size:.82rem"><?= htmlspecialchars($r['ip'] ?? '') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
