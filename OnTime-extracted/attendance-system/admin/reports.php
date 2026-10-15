<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}
$stmt = $db->prepare("
    SELECT e.employee_code, e.full_name, COUNT(a.id) AS recorded_days,
           SUM(a.attendance_status = 'present') AS present_days,
           SUM(a.attendance_status = 'late') AS late_days,
           SUM(a.attendance_status = 'absent') AS absent_days,
           ROUND(SUM(COALESCE(a.total_hours, 0)), 2) AS total_hours
    FROM employees e LEFT JOIN attendance a
      ON a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ?
    WHERE e.employment_status <> 'inactive'
    GROUP BY e.id ORDER BY e.full_name
");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

adminPageStart('Reports', 'Attendance summary by employee for the selected period.');
?>
<div class="card"><div class="card-body"><form method="get" class="form-inline">
    <label class="form-label">From <input class="form-control" type="date" name="from" value="<?= adminEscape($from) ?>"></label>
    <label class="form-label">To <input class="form-control" type="date" name="to" value="<?= adminEscape($to) ?>"></label>
    <button class="btn btn-primary">Generate</button>
    <a class="btn btn-secondary" href="<?= adminEscape(appUrl('admin/records.php?' . http_build_query(['from' => $from, 'to' => $to]))) ?>">Open detailed records</a>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Recorded Days</th><th>Present</th><th>Late</th><th>Absent</th><th>Total Hours</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6">No employees found.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?= adminEscape($row['full_name']) ?><br><?= adminEscape($row['employee_code']) ?></td><td><?= (int)$row['recorded_days'] ?></td><td><?= (int)$row['present_days'] ?></td><td><?= (int)$row['late_days'] ?></td><td><?= (int)$row['absent_days'] ?></td><td><?= number_format((float)$row['total_hours'], 2) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
