<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (employeePostIsValid()) {
    $leaveType = (string)($_POST['leave_type'] ?? '');
    $startDate = (string)($_POST['start_date'] ?? '');
    $endDate = (string)($_POST['end_date'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));
    $validTypes = ['sick','vacation','emergency','maternity','paternity','unpaid'];
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!in_array($leaveType, $validTypes, true) || !$start || $start->format('Y-m-d') !== $startDate || !$end || $end->format('Y-m-d') !== $endDate || $endDate < $startDate || $reason === '' || strlen($reason) > 5000) {
        employeeRedirect('leave_request.php', 'danger', 'Enter a valid leave type, date range, and reason.');
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status IN ('pending','approved') AND start_date <= ? AND end_date >= ?");
        $stmt->execute([$employeeId, $endDate, $startDate]);
        if ((int)$stmt->fetchColumn() > 0) {
            employeeRedirect('leave_request.php', 'danger', 'These dates overlap an existing pending or approved leave request.');
        }
        $stmt = $db->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$employeeId, $leaveType, $startDate, $endDate, $reason]);
        $leaveId = (int)$db->lastInsertId();
        logAudit($db, (int)$_SESSION['user_id'], 'Submit Leave Request', 'Employee submitted a leave request.', 'leave_requests', $leaveId);
        employeeRedirect('leave_request.php', 'success', 'Leave request submitted for review.');
    } catch (Throwable $e) {
        error_log('Leave request creation failed: ' . $e->getMessage());
        employeeRedirect('leave_request.php', 'danger', 'Unable to submit your request.');
    }
}

$today = date('Y-m-d');
employeePageStart('Request Leave', 'Submit a leave request for administrator review.');
?>
<div class="card"><div class="card-body">
    <form method="post" class="form-grid"><?= csrfInput() ?>
        <label class="form-group"><span class="form-label">Leave type</span><select class="form-control" name="leave_type" required><?php foreach (['sick','vacation','emergency','maternity','paternity','unpaid'] as $type): ?><option value="<?= $type ?>"><?= employeeEscape(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
        <label class="form-group"><span class="form-label">Start date</span><input class="form-control" type="date" name="start_date" min="<?= $today ?>" required></label>
        <label class="form-group"><span class="form-label">End date</span><input class="form-control" type="date" name="end_date" min="<?= $today ?>" required></label>
        <label class="form-group form-grid-full"><span class="form-label">Reason</span><textarea class="form-control" name="reason" rows="5" maxlength="5000" required></textarea></label>
        <div><button class="btn btn-primary">Submit request</button></div>
    </form>
</div></div>
<?php employeePageEnd(); ?>
