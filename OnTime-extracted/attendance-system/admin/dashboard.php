<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$today = date('Y-m-d');
$stats = [
    'employees' => (int)$db->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'active'")->fetchColumn(),
    'present' => 0,
    'pending_registrations' => (int)$db->query("SELECT COUNT(*) FROM registrations WHERE status = 'pending'")->fetchColumn(),
    'pending_leaves' => (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn(),
];
$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE attendance_date = ? AND attendance_status IN ('present','late','half_day')");
$stmt->execute([$today]);
$stats['present'] = (int)$stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT a.attendance_date, a.actual_time_in, a.actual_time_out, a.attendance_status, e.employee_code, e.full_name
    FROM attendance a JOIN employees e ON e.id = a.employee_id
    WHERE a.attendance_date = ? ORDER BY a.actual_time_in DESC LIMIT 10
");
$stmt->execute([$today]);
$recentAttendance = $stmt->fetchAll();

adminPageStart('Admin Dashboard', 'Attendance overview for ' . date('F j, Y'));
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue"><?= uiIcon('users') ?></div><div class="stat-info"><div class="stat-value"><?= $stats['employees'] ?></div><div class="stat-label">Active Employees</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><?= uiIcon('clock') ?></div><div class="stat-info"><div class="stat-value"><?= $stats['present'] ?></div><div class="stat-label">Checked In Today</div></div></div>
    <div class="stat-card"><div class="stat-icon orange"><?= uiIcon('clipboard') ?></div><div class="stat-info"><div class="stat-value"><?= $stats['pending_registrations'] ?></div><div class="stat-label">Pending Applications</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><?= uiIcon('calendar') ?></div><div class="stat-info"><div class="stat-value"><?= $stats['pending_leaves'] ?></div><div class="stat-label">Pending Leave Requests</div></div></div>
</div>
<div class="card">
    <div class="card-header"><h3>Today's Attendance</h3><a href="<?= adminEscape(appUrl('admin/attendance.php')) ?>">View all</a></div>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Code</th><th>Time In</th><th>Time Out</th><th>Status</th></tr></thead><tbody>
    <?php if (!$recentAttendance): ?><tr><td colspan="5">No attendance has been recorded today.</td></tr><?php endif; ?>
    <?php foreach ($recentAttendance as $row): ?><tr>
        <td><?= adminEscape($row['full_name']) ?></td><td><?= adminEscape($row['employee_code']) ?></td>
        <td><?= $row['actual_time_in'] ? adminEscape(date('g:i A', strtotime($row['actual_time_in']))) : '—' ?></td>
        <td><?= $row['actual_time_out'] ? adminEscape(date('g:i A', strtotime($row['actual_time_out']))) : '—' ?></td>
        <td><?= adminEscape(ucwords(str_replace('_', ' ', $row['attendance_status']))) ?></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php adminPageEnd(); ?>
