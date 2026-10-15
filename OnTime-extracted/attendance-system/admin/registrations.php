<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (adminPostIsValid()) {
    $registrationId = filter_input(INPUT_POST, 'registration_id', FILTER_VALIDATE_INT);
    $decision = (string)($_POST['decision'] ?? '');
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));
    if (!$registrationId || !in_array($decision, ['approve', 'reject'], true) || ($decision === 'reject' && $reason === '')) {
        adminRedirect('registrations.php', 'danger', 'Choose a valid review action and provide a rejection reason when rejecting.');
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT * FROM registrations WHERE id = ? AND status = 'pending' FOR UPDATE");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch();
        if (!$registration) {
            throw new RuntimeException('This application has already been reviewed or no longer exists.');
        }

        if ($decision === 'approve') {
            $code = $registration['employee_type'] === 'instructor'
                ? generateProfessorCode($db)
                : generateStaffCode($db);
            $username = generateUsername($registration['full_name'], $code, $registration['employee_type']);
            $stmt = $db->prepare("
                INSERT INTO employees
                    (employee_code, full_name, email, contact_number, honorific, age, gender, employee_type,
                     department_id, specialization, date_hired, employment_status, registration_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', ?)
            ");
            $stmt->execute([
                $code, $registration['full_name'], $registration['email'], $registration['contact_number'],
                $registration['honorific'], $registration['age'], $registration['gender'],
                $registration['employee_type'], $registration['department_id'],
                $registration['specialization'], $registrationId,
            ]);
            $employeeId = (int)$db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO users (username, password, role, employee_id, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $registration['password_hash'], $registration['employee_type'], $employeeId]);
            $stmt = $db->prepare("UPDATE registrations SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $stmt->execute([(int)$_SESSION['user_id'], $registrationId]);
            $actionDescription = "Approved application for {$registration['full_name']} and created account {$username}.";
        } else {
            $stmt = $db->prepare("UPDATE registrations SET status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
            $stmt->execute([$reason, (int)$_SESSION['user_id'], $registrationId]);
            $actionDescription = "Rejected application for {$registration['full_name']}.";
        }

        adminAudit($db, ucfirst($decision) . ' Registration', $actionDescription, 'registrations', (int)$registrationId);
        $db->commit();
        adminRedirect('registrations.php', 'success', $decision === 'approve'
            ? "Application approved. New account username: {$username} (surname + employee code)."
            : 'Application rejected.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Registration review failed: ' . $e->getMessage());
        adminRedirect('registrations.php', 'danger', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to review this application.');
    }
}

$status = (string)($_GET['status'] ?? 'pending');
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending';
}
$sql = "SELECT r.*, d.name AS department_name FROM registrations r LEFT JOIN departments d ON d.id = r.department_id";
if ($status !== 'all') {
    $sql .= " WHERE r.status = " . $db->quote($status);
}
$sql .= ' ORDER BY r.applied_at DESC LIMIT 300';
$registrations = $db->query($sql)->fetchAll();

adminPageStart('Registration Applications', 'Review applications and create accounts for approved employees.');
?>
<div class="card"><div class="card-body">
    <form method="get" class="form-inline">
        <label class="form-label" for="status">Status</label>
        <select class="form-control" id="status" name="status">
            <?php foreach ($allowedStatuses as $option): ?><option value="<?= adminEscape($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= adminEscape(ucfirst($option)) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Filter</button>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Applicant</th><th>Type / Department</th><th>Applied</th><th>Status</th><th>Review</th></tr></thead><tbody>
<?php if (!$registrations): ?><tr><td colspan="5">No applications found.</td></tr><?php endif; ?>
<?php foreach ($registrations as $row): ?><tr>
    <td><strong><?= adminEscape($row['full_name']) ?></strong><br><?= adminEscape($row['email']) ?><br><?= adminEscape($row['contact_number']) ?></td>
    <td><?= adminEscape(ucfirst($row['employee_type'])) ?><br><?= adminEscape($row['department_name'] ?? '—') ?></td>
    <td><?= adminEscape(date('M j, Y', strtotime($row['applied_at']))) ?></td>
    <td><?= adminEscape(ucfirst($row['status'])) ?><?= $row['rejection_reason'] ? '<br>' . adminEscape($row['rejection_reason']) : '' ?></td>
    <td><?php if ($row['status'] === 'pending'): ?>
        <form method="post" class="stacked-actions">
            <?= csrfInput() ?><input type="hidden" name="registration_id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-success" name="decision" value="approve" type="submit">Approve</button>
            <input class="form-control" name="rejection_reason" maxlength="1000" placeholder="Reason if rejecting">
            <button class="btn btn-danger" name="decision" value="reject" type="submit">Reject</button>
        </form>
    <?php else: ?>Reviewed <?= $row['reviewed_at'] ? adminEscape(date('M j, Y', strtotime($row['reviewed_at']))) : '' ?><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
