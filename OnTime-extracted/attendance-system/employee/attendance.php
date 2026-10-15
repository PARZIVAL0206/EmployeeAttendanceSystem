<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (employeePostIsValid()) {
    $action = (string)($_POST['action'] ?? '');
    $today = date('Y-m-d');
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? FOR UPDATE");
        $stmt->execute([$employeeId, $today]);
        $record = $stmt->fetch();
        $now = date('Y-m-d H:i:s');

        if ($action === 'time_in') {
            if ($record && $record['actual_time_in']) {
                throw new RuntimeException('You have already recorded your time in today.');
            }
            $schedule = getCurrentSchedule($db, $employeeId);
            if (!$schedule) {
                throw new RuntimeException('No active schedule is assigned. Please contact your administrator.');
            }
            if (!isWorkDay($schedule, $today)) {
                throw new RuntimeException('Today is not a work day in your current schedule.');
            }
            $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND start_date <= ? AND end_date >= ?");
            $stmt->execute([$employeeId, $today, $today]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('You have approved leave for today and cannot clock in.');
            }
            $attendanceStatus = determineAttendanceStatus($schedule['time_in'], $now, (int)$schedule['grace_period_minutes']);
            if ($record) {
                $stmt = $db->prepare("UPDATE attendance SET scheduled_time_in = ?, scheduled_time_out = ?, actual_time_in = ?, attendance_status = ?, completion_status = 'pending' WHERE id = ?");
                $stmt->execute([$schedule['time_in'], $schedule['time_out'], $now, $attendanceStatus, (int)$record['id']]);
                $attendanceId = (int)$record['id'];
            } else {
                $stmt = $db->prepare("INSERT INTO attendance (employee_id, attendance_date, scheduled_time_in, scheduled_time_out, actual_time_in, attendance_status, completion_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$employeeId, $today, $schedule['time_in'], $schedule['time_out'], $now, $attendanceStatus]);
                $attendanceId = (int)$db->lastInsertId();
            }
            logAudit($db, (int)$_SESSION['user_id'], 'Time In', 'Employee recorded time in.', 'attendance', $attendanceId);
            $db->commit();
            employeeRedirect('attendance.php', 'success', 'Time in recorded at ' . date('g:i A', strtotime($now)) . '.');
        }

        if ($action === 'time_out') {
            if (!$record || !$record['actual_time_in']) {
                throw new RuntimeException('Record your time in before recording time out.');
            }
            if ($record['actual_time_out']) {
                throw new RuntimeException('You have already recorded your time out today.');
            }
            $schedule = getCurrentSchedule($db, $employeeId);
            $breakMinutes = (int)($schedule['break_duration_minutes'] ?? 0);
            $workedHours = max(0, calculateHours($record['actual_time_in'], $now) - ($breakMinutes / 60));
            $completion = determineCompletionStatus(
                $record['scheduled_time_out'] ?? $schedule['time_out'] ?? '00:00:00',
                $now,
                (float)($schedule['required_hours'] ?? 8),
                $workedHours
            );
            $stmt = $db->prepare("UPDATE attendance SET actual_time_out = ?, total_hours = ?, completion_status = ? WHERE id = ? AND actual_time_out IS NULL");
            $stmt->execute([$now, round($workedHours, 2), $completion, (int)$record['id']]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Your time out was already recorded.');
            }
            logAudit($db, (int)$_SESSION['user_id'], 'Time Out', 'Employee recorded time out.', 'attendance', (int)$record['id']);
            $db->commit();
            employeeRedirect('attendance.php', 'success', 'Time out recorded. Total hours: ' . number_format($workedHours, 2) . '.');
        }

        throw new RuntimeException('Unknown attendance action.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (!$e instanceof RuntimeException) {
            error_log('Attendance punch failed: ' . $e->getMessage());
        }
        employeeRedirect('attendance.php', 'danger', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to record attendance.');
    }
}

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
$stmt->execute([$employeeId, $today]);
$record = $stmt->fetch();
$schedule = getCurrentSchedule($db, $employeeId);

employeePageStart('My Attendance', 'Record your time in and time out for today.');
?>
<div class="clock-card">
    <div class="clock-display"><?= employeeEscape(date('g:i A')) ?></div>
    <div class="clock-date"><?= employeeEscape(date('l, F j, Y')) ?></div>
    <?php if ($schedule): ?><p><?= employeeEscape($schedule['schedule_name']) ?> · <?= employeeEscape(date('g:i A', strtotime($schedule['time_in']))) ?>–<?= employeeEscape(date('g:i A', strtotime($schedule['time_out']))) ?></p><?php else: ?><p>No schedule assigned. Contact your administrator.</p><?php endif; ?>
    <div class="time-action-buttons">
        <form method="post"><?= csrfInput() ?><input type="hidden" name="action" value="time_in"><button class="btn btn-time-in" <?= (!$schedule || ($record && $record['actual_time_in'])) ? 'disabled' : '' ?>>Time In</button></form>
        <form method="post"><?= csrfInput() ?><input type="hidden" name="action" value="time_out"><button class="btn btn-time-out" <?= (!$record || !$record['actual_time_in'] || $record['actual_time_out']) ? 'disabled' : '' ?>>Time Out</button></form>
    </div>
</div>
<div class="card"><div class="card-header"><h3>Today's Record</h3></div><div class="card-body">
    <?php if ($record): ?>
        <p><strong>Time in:</strong> <?= $record['actual_time_in'] ? employeeEscape(date('g:i:s A', strtotime($record['actual_time_in']))) : 'Not recorded' ?></p>
        <p><strong>Time out:</strong> <?= $record['actual_time_out'] ? employeeEscape(date('g:i:s A', strtotime($record['actual_time_out']))) : 'Not recorded' ?></p>
        <p><strong>Status:</strong> <?= employeeEscape(ucwords(str_replace('_', ' ', $record['attendance_status']))) ?> / <?= employeeEscape(ucwords(str_replace('_', ' ', $record['completion_status']))) ?></p>
        <p><strong>Total hours:</strong> <?= $record['total_hours'] !== null ? number_format((float)$record['total_hours'], 2) : 'Pending' ?></p>
    <?php else: ?><p>No attendance has been recorded today.</p><?php endif; ?>
</div></div>
<?php employeePageEnd(); ?>
