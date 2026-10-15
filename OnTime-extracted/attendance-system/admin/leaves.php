<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (adminPostIsValid()) {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = (string)($_POST['decision'] ?? '');
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));
    if (!$requestId || !in_array($decision, ['approved', 'rejected'], true) || ($decision === 'rejected' && $reason === '')) {
        adminRedirect('leaves.php', 'danger', 'Choose a valid decision and provide a reason when rejecting.');
    }
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE leave_requests SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$decision, $decision === 'rejected' ? $reason : null, (int)$_SESSION['user_id'], $requestId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('That request was already reviewed.');
        }
        $stmt = $db->prepare("SELECT employee_id FROM leave_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $owner = (int)$stmt->fetchColumn();
        $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->execute([$owner]);
        $userId = (int)$stmt->fetchColumn();
        if ($userId) {
            addNotification($db, $userId, 'Leave request ' . $decision, $decision === 'approved' ? 'Your leave request was approved.' : 'Your leave request was rejected: ' . $reason);
        }
        adminAudit($db, ucfirst($decision) . ' Leave', "Reviewed leave request #{$requestId}: {$decision}.", 'leave_requests', (int)$requestId);
        $db->commit();
        adminRedirect('leaves.php', 'success', 'Leave request reviewed.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof RuntimeException) {
            adminRedirect('leaves.php', 'warning', $e->getMessage());
        }
        error_log('Leave review failed: ' . $e->getMessage());
        adminRedirect('leaves.php', 'danger', 'Unable to review this leave request.');
    }
}

$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}
$query = "
    SELECT l.*, e.full_name, e.employee_code, d.name AS department_name
    FROM leave_requests l JOIN employees e ON e.id = l.employee_id
    LEFT JOIN departments d ON d.id = e.department_id
";
if ($status !== 'all') {
    $query .= ' WHERE l.status = ' . $db->quote($status);
}
$query .= ' ORDER BY l.created_at DESC LIMIT 300';
$requests = $db->query($query)->fetchAll();

adminPageStart('Leave Management', 'Review employee leave requests.');
?>
<div class="card"><div class="card-body"><form method="get" class="form-inline"><label class="form-label">Status <select class="form-control" name="status"><?php foreach (['pending','approved','rejected','all'] as $value): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= adminEscape(ucfirst($value)) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary">Filter</button></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Leave</th><th>Dates</th><th>Reason</th><th>Status</th><th>Review</th></tr></thead><tbody>
<?php if (!$requests): ?><tr><td colspan="6">No leave requests found.</td></tr><?php endif; ?>
<?php foreach ($requests as $row): ?><tr>
    <td><?= adminEscape($row['full_name']) ?><br><?= adminEscape($row['employee_code']) ?><br><?= adminEscape($row['department_name'] ?? '—') ?></td>
    <td><?= adminEscape(ucfirst($row['leave_type'])) ?></td>
    <td><?= adminEscape(date('M j, Y', strtotime($row['start_date']))) ?> – <?= adminEscape(date('M j, Y', strtotime($row['end_date']))) ?></td>
    <td><?= nl2br(adminEscape($row['reason'])) ?><?= $row['rejection_reason'] ? '<br><small>' . adminEscape($row['rejection_reason']) . '</small>' : '' ?></td>
    <td><?= adminEscape(ucfirst($row['status'])) ?></td>
    <td><?php if ($row['status'] === 'pending'): ?><form method="post" class="stacked-actions"><?= csrfInput() ?><input type="hidden" name="request_id" value="<?= (int)$row['id'] ?>">
        <button class="btn btn-success" name="decision" value="approved">Approve</button><input class="form-control" name="rejection_reason" placeholder="Reason if rejecting">
        <button class="btn btn-danger" name="decision" value="rejected">Reject</button></form><?php else: ?><?= $row['reviewed_at'] ? adminEscape(date('M j, Y', strtotime($row['reviewed_at']))) : 'Reviewed' ?><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
