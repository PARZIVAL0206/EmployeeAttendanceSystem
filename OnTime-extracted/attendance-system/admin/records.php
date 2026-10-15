<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}
$employeeId = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $db->prepare("
    SELECT a.attendance_date, e.employee_code, e.full_name, a.scheduled_time_in, a.scheduled_time_out,
           a.actual_time_in, a.actual_time_out, a.total_hours, a.attendance_status, a.completion_status
    FROM attendance a JOIN employees e ON e.id = a.employee_id
    WHERE a.attendance_date BETWEEN ? AND ? AND (? = 0 OR e.id = ?)
    ORDER BY a.attendance_date DESC, e.full_name LIMIT 2000
");
$stmt->execute([$from, $to, $employeeId, $employeeId]);
$records = $stmt->fetchAll();
$employees = $db->query("SELECT id, full_name FROM employees ORDER BY full_name")->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-records-' . $from . '-to-' . $to . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Employee Code', 'Employee', 'Scheduled In', 'Scheduled Out', 'Actual In', 'Actual Out', 'Hours', 'Status', 'Completion']);
    foreach ($records as $row) {
        fputcsv($output, array_values($row));
    }
    fclose($output);
    exit;
}

adminPageStart('Attendance Records', 'Filter and export recorded attendance.');
?>
<div class="card"><div class="card-body">
    <form method="get" class="form-inline"><label class="form-label">From <input class="form-control" type="date" name="from" value="<?= adminEscape($from) ?>"></label>
        <label class="form-label">To <input class="form-control" type="date" name="to" value="<?= adminEscape($to) ?>"></label>
        <label class="form-label">Employee <select class="form-control" name="employee_id"><option value="0">All employees</option><?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>" <?= $employeeId === (int)$employee['id'] ? 'selected' : '' ?>><?= adminEscape($employee['full_name']) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary">Filter</button>
        <a class="btn btn-secondary" href="?<?= http_build_query(['from' => $from, 'to' => $to, 'employee_id' => $employeeId, 'export' => 'csv']) ?>">Export CSV</a>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Date</th><th>Employee</th><th>Scheduled</th><th>Actual</th><th>Hours</th><th>Status</th></tr></thead><tbody>
<?php if (!$records): ?><tr><td colspan="6">No attendance records in this date range.</td></tr><?php endif; ?>
<?php foreach ($records as $row): ?><tr>
    <td><?= adminEscape(date('M j, Y', strtotime($row['attendance_date']))) ?></td>
    <td><?= adminEscape($row['full_name']) ?><br><?= adminEscape($row['employee_code']) ?></td>
    <td><?= adminEscape(($row['scheduled_time_in'] ?? '—') . ' – ' . ($row['scheduled_time_out'] ?? '—')) ?></td>
    <td><?= $row['actual_time_in'] ? adminEscape(date('g:i A', strtotime($row['actual_time_in']))) : '—' ?> – <?= $row['actual_time_out'] ? adminEscape(date('g:i A', strtotime($row['actual_time_out']))) : '—' ?></td>
    <td><?= $row['total_hours'] !== null ? number_format((float)$row['total_hours'], 2) : '—' ?></td>
    <td><?= adminEscape(ucwords(str_replace('_', ' ', $row['attendance_status']))) ?> / <?= adminEscape(ucwords(str_replace('_', ' ', $row['completion_status']))) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
