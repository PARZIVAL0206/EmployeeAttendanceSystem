<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$stmt = $db->prepare("
    SELECT e.employee_code, e.full_name, d.name AS department_name,
           a.actual_time_in, a.actual_time_out, a.attendance_status, a.completion_status, a.total_hours
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = ?
    WHERE e.employment_status = 'active'
    ORDER BY e.full_name
");
$stmt->execute([$date]);
$rows = $stmt->fetchAll();

adminPageStart('Attendance Monitoring', 'Daily check-in status for active employees.');
?>
<div class="card"><div class="card-body"><form method="get" class="form-inline">
    <label class="form-label" for="date">Attendance date</label><input class="form-control" type="date" id="date" name="date" value="<?= adminEscape($date) ?>">
    <button class="btn btn-primary">View</button>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Department</th><th>Time In</th><th>Time Out</th><th>Attendance</th><th>Completion</th><th>Hours</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
    <td><?= adminEscape($row['full_name']) ?><br><?= adminEscape($row['employee_code']) ?></td>
    <td><?= adminEscape($row['department_name'] ?? '—') ?></td>
    <td><?= $row['actual_time_in'] ? adminEscape(date('g:i A', strtotime($row['actual_time_in']))) : '—' ?></td>
    <td><?= $row['actual_time_out'] ? adminEscape(date('g:i A', strtotime($row['actual_time_out']))) : '—' ?></td>
    <td><?= adminEscape(ucwords(str_replace('_', ' ', $row['attendance_status'] ?? 'Not recorded'))) ?></td>
    <td><?= adminEscape(ucwords(str_replace('_', ' ', $row['completion_status'] ?? '—'))) ?></td>
    <td><?= $row['total_hours'] !== null ? number_format((float)$row['total_hours'], 2) : '—' ?></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7">No active employees found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
