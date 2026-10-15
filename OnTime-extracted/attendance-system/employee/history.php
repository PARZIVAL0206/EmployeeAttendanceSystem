<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}
$stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC LIMIT 500");
$stmt->execute([$employeeId, $from, $to]);
$records = $stmt->fetchAll();

employeePageStart('Attendance History', 'Review your recorded attendance.');
?>
<div class="card"><div class="card-body"><form method="get" class="form-inline">
    <label class="form-label">From <input class="form-control" type="date" name="from" value="<?= employeeEscape($from) ?>"></label>
    <label class="form-label">To <input class="form-control" type="date" name="to" value="<?= employeeEscape($to) ?>"></label>
    <button class="btn btn-primary">Filter</button>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th></tr></thead><tbody>
<?php if (!$records): ?><tr><td colspan="5">No records in this date range.</td></tr><?php endif; ?>
<?php foreach ($records as $row): ?><tr><td><?= employeeEscape(date('M j, Y', strtotime($row['attendance_date']))) ?></td>
    <td><?= $row['actual_time_in'] ? employeeEscape(date('g:i A', strtotime($row['actual_time_in']))) : '—' ?></td>
    <td><?= $row['actual_time_out'] ? employeeEscape(date('g:i A', strtotime($row['actual_time_out']))) : '—' ?></td>
    <td><?= $row['total_hours'] !== null ? number_format((float)$row['total_hours'], 2) : '—' ?></td>
    <td><?= employeeEscape(ucwords(str_replace('_', ' ', $row['attendance_status']))) ?> / <?= employeeEscape(ucwords(str_replace('_', ' ', $row['completion_status']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php employeePageEnd(); ?>
