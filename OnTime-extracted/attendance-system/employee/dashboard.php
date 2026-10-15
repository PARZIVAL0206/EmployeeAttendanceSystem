<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
$stmt->execute([$employeeId, $today]);
$todayRecord = $stmt->fetch();
$schedule = getCurrentSchedule($db, $employeeId);
$stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
$stmt->execute([$employeeId]);
$pendingLeaves = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT attendance_date, actual_time_in, actual_time_out, attendance_status, total_hours FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 7");
$stmt->execute([$employeeId]);
$recent = $stmt->fetchAll();

employeePageStart('Employee Dashboard', 'Welcome, ' . ($_SESSION['full_name'] ?? 'Employee') . '.');
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue"><?= uiIcon('calendar') ?></div><div class="stat-info"><div class="stat-value"><?= $schedule ? employeeEscape($schedule['schedule_name']) : '—' ?></div><div class="stat-label">Current Schedule</div></div></div>
    <div class="stat-card"><div class="stat-icon orange"><?= uiIcon('clipboard') ?></div><div class="stat-info"><div class="stat-value"><?= $pendingLeaves ?></div><div class="stat-label">Pending Leave Requests</div></div></div>
</div>
<div class="clock-card">
    <div class="clock-display" id="liveClock"><?= date('g:i:s A') ?></div>
    <div class="clock-date"><?= employeeEscape(date('l, F j, Y')) ?></div>
    <?php if ($schedule): ?><p><?= employeeEscape($schedule['schedule_name']) ?>: <?= employeeEscape(date('g:i A', strtotime($schedule['time_in']))) ?>–<?= employeeEscape(date('g:i A', strtotime($schedule['time_out']))) ?></p><?php endif; ?>
    <div class="time-action-buttons">
        <?php if (!$todayRecord || !$todayRecord['actual_time_in']): ?><a class="btn btn-time-in" href="<?= employeeEscape(appUrl('employee/attendance.php')) ?>">Time In</a>
        <?php elseif (!$todayRecord['actual_time_out']): ?><a class="btn btn-time-out" href="<?= employeeEscape(appUrl('employee/attendance.php')) ?>">Time Out</a>
        <?php else: ?><button class="btn btn-time-in" disabled>Attendance Complete</button><?php endif; ?>
    </div>
</div>
<div class="card"><div class="card-header"><h3>Recent Attendance</h3><a href="<?= employeeEscape(appUrl('employee/history.php')) ?>">Full history</a></div>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th></tr></thead><tbody>
    <?php if (!$recent): ?><tr><td colspan="5">No attendance records yet.</td></tr><?php endif; ?>
    <?php foreach ($recent as $row): ?><tr><td><?= employeeEscape(date('M j, Y', strtotime($row['attendance_date']))) ?></td>
        <td><?= $row['actual_time_in'] ? employeeEscape(date('g:i A', strtotime($row['actual_time_in']))) : '—' ?></td>
        <td><?= $row['actual_time_out'] ? employeeEscape(date('g:i A', strtotime($row['actual_time_out']))) : '—' ?></td>
        <td><?= $row['total_hours'] !== null ? number_format((float)$row['total_hours'], 2) : '—' ?></td><td><?= employeeEscape(ucfirst($row['attendance_status'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<script>
setInterval(function () {
    var clock = document.getElementById('liveClock');
    if (clock) clock.textContent = new Date().toLocaleTimeString();
}, 1000);
</script>
<?php employeePageEnd(); ?>
