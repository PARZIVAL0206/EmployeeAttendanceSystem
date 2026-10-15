<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$stmt = $db->prepare("SELECT leave_type, start_date, end_date, reason, status, rejection_reason, created_at FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC LIMIT 300");
$stmt->execute([$employeeId]);
$requests = $stmt->fetchAll();

employeePageStart('Leave History', 'Track the status of your leave requests.');
?>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Submitted</th><th>Type</th><th>Dates</th><th>Reason</th><th>Status</th></tr></thead><tbody>
<?php if (!$requests): ?><tr><td colspan="5">You have not submitted a leave request.</td></tr><?php endif; ?>
<?php foreach ($requests as $row): ?><tr>
    <td><?= employeeEscape(date('M j, Y', strtotime($row['created_at']))) ?></td><td><?= employeeEscape(ucfirst($row['leave_type'])) ?></td>
    <td><?= employeeEscape(date('M j, Y', strtotime($row['start_date']))) ?> – <?= employeeEscape(date('M j, Y', strtotime($row['end_date']))) ?></td>
    <td><?= nl2br(employeeEscape($row['reason'])) ?><?= $row['rejection_reason'] ? '<br><small>Reason: ' . employeeEscape($row['rejection_reason']) . '</small>' : '' ?></td>
    <td><?= employeeEscape(ucfirst($row['status'])) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php employeePageEnd(); ?>
