<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$logs = $db->query("
    SELECT a.*, u.username
    FROM audit_logs a JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC, a.id DESC LIMIT 500
")->fetchAll();

adminPageStart('Audit Logs', 'Recent account and system activity.');
?>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>Record</th><th>IP</th></tr></thead><tbody>
<?php if (!$logs): ?><tr><td colspan="6">No audit events recorded.</td></tr><?php endif; ?>
<?php foreach ($logs as $row): ?><tr>
    <td><?= adminEscape(date('M j, Y g:i A', strtotime($row['created_at']))) ?></td>
    <td><?= adminEscape($row['username']) ?></td><td><?= adminEscape($row['action']) ?></td><td><?= adminEscape($row['description'] ?? '') ?></td>
    <td><?= adminEscape($row['affected_table'] ?? '—') ?><?= $row['affected_id'] ? ' #' . (int)$row['affected_id'] : '' ?></td><td><?= adminEscape($row['ip_address'] ?? '—') ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
