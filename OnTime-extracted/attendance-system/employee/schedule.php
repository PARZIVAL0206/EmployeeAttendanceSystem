<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

$current = getCurrentSchedule($db, $employeeId);
$stmt = $db->prepare("
    SELECT es.effective_date, s.schedule_name, s.time_in, s.time_out, s.work_days,
           s.grace_period_minutes, s.break_duration_minutes, s.required_hours, s.is_overnight
    FROM employee_schedules es JOIN schedules s ON s.id = es.schedule_id
    WHERE es.employee_id = ? ORDER BY es.effective_date DESC
");
$stmt->execute([$employeeId]);
$assignments = $stmt->fetchAll();

employeePageStart('My Schedule', 'Your assigned work schedules.');
?>
<?php if ($current): ?><div class="card"><div class="card-header"><h3>Current Schedule</h3></div><div class="card-body">
    <h2><?= employeeEscape($current['schedule_name']) ?></h2>
    <p><?= employeeEscape(date('g:i A', strtotime($current['time_in']))) ?> – <?= employeeEscape(date('g:i A', strtotime($current['time_out']))) ?><?= $current['is_overnight'] ? ' (overnight)' : '' ?></p>
    <p><strong>Work days:</strong> <?= employeeEscape(implode(', ', json_decode($current['work_days'], true) ?: [])) ?></p>
    <p><strong>Grace:</strong> <?= (int)$current['grace_period_minutes'] ?> minutes · <strong>Break:</strong> <?= (int)$current['break_duration_minutes'] ?> minutes · <strong>Required:</strong> <?= number_format((float)$current['required_hours'], 2) ?> hours</p>
    <p><strong>Effective since:</strong> <?= employeeEscape(date('M j, Y', strtotime($current['effective_date']))) ?></p>
</div></div><?php else: ?><div class="alert alert-info">No current schedule is assigned. Please contact your administrator.</div><?php endif; ?>
<div class="card"><div class="card-header"><h3>Schedule History</h3></div><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Effective</th><th>Schedule</th><th>Hours</th><th>Work days</th></tr></thead><tbody>
<?php if (!$assignments): ?><tr><td colspan="4">No schedule assignments found.</td></tr><?php endif; ?>
<?php foreach ($assignments as $row): ?><tr><td><?= employeeEscape(date('M j, Y', strtotime($row['effective_date']))) ?></td><td><?= employeeEscape($row['schedule_name']) ?> (<?= employeeEscape(date('g:i A', strtotime($row['time_in']))) ?>–<?= employeeEscape(date('g:i A', strtotime($row['time_out']))) ?>)</td><td><?= number_format((float)$row['required_hours'], 2) ?></td><td><?= employeeEscape(implode(', ', json_decode($row['work_days'], true) ?: [])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php employeePageEnd(); ?>
