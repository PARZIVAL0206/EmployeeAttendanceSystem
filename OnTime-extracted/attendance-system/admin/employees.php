<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (adminPostIsValid()) {
    $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $status = (string)($_POST['employment_status'] ?? '');
    if (!$employeeId || !in_array($status, ['active', 'inactive', 'on_leave'], true)) {
        adminRedirect('employees.php', 'danger', 'Invalid employee update.');
    }
    try {
        $stmt = $db->prepare("UPDATE employees SET employment_status = ? WHERE id = ?");
        $stmt->execute([$status, $employeeId]);
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE employee_id = ?");
        $stmt->execute([$status === 'inactive' ? 'inactive' : 'active', $employeeId]);
        adminAudit($db, 'Update Employee Status', "Changed employee #{$employeeId} to {$status}.", 'employees', (int)$employeeId);
        adminRedirect('employees.php', 'success', 'Employee status updated.');
    } catch (Throwable $e) {
        error_log('Employee status update failed: ' . $e->getMessage());
        adminRedirect('employees.php', 'danger', 'Unable to update employee status.');
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$stmt = $db->prepare("
    SELECT e.id, e.employee_code, e.full_name, e.email, e.employee_type, e.employment_status,
           d.name AS department_name, p.title AS position_title, u.username
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN positions p ON p.id = e.position_id
    LEFT JOIN users u ON u.employee_id = e.id
    WHERE (? = '' OR e.full_name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ?)
    ORDER BY e.full_name LIMIT 500
");
$term = '%' . $search . '%';
$stmt->execute([$search, $term, $term, $term]);
$employees = $stmt->fetchAll();

adminPageStart('Employee Management', 'Search employee profiles and manage account access.');
?>
<div class="card"><div class="card-body">
    <form method="get" class="form-inline"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= adminEscape($search) ?>" placeholder="Name, employee code, or email"><button class="btn btn-primary">Search</button></form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Account</th><th>Department / Position</th><th>Type</th><th>Status</th><th>Update</th></tr></thead><tbody>
<?php if (!$employees): ?><tr><td colspan="6">No employees found.</td></tr><?php endif; ?>
<?php foreach ($employees as $row): ?><tr>
    <td><strong><?= adminEscape($row['full_name']) ?></strong><br><?= adminEscape($row['employee_code']) ?><br><?= adminEscape($row['email']) ?></td>
    <td><?= adminEscape($row['username'] ?? 'No account') ?></td>
    <td><?= adminEscape($row['department_name'] ?? '—') ?><br><?= adminEscape($row['position_title'] ?? '—') ?></td>
    <td><?= adminEscape(ucfirst($row['employee_type'])) ?></td><td><?= adminEscape(ucwords(str_replace('_', ' ', $row['employment_status']))) ?></td>
    <td><form method="post" class="form-inline"><?= csrfInput() ?><input type="hidden" name="employee_id" value="<?= (int)$row['id'] ?>">
        <select class="form-control" name="employment_status"><?php foreach (['active','inactive','on_leave'] as $value): ?><option value="<?= $value ?>" <?= $row['employment_status'] === $value ? 'selected' : '' ?>><?= adminEscape(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary">Save</button>
    </form></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
