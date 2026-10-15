<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (adminPostIsValid()) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create_schedule') {
            $name = trim((string)($_POST['schedule_name'] ?? ''));
            $timeIn = (string)($_POST['time_in'] ?? '');
            $timeOut = (string)($_POST['time_out'] ?? '');
            $days = array_values(array_intersect(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], (array)($_POST['work_days'] ?? [])));
            $grace = filter_input(INPUT_POST, 'grace_period_minutes', FILTER_VALIDATE_INT);
            $break = filter_input(INPUT_POST, 'break_duration_minutes', FILTER_VALIDATE_INT);
            $hours = filter_input(INPUT_POST, 'required_hours', FILTER_VALIDATE_FLOAT);
            $overnight = isset($_POST['is_overnight']) ? 1 : 0;
            if ($name === '' || !preg_match('/^\d{2}:\d{2}$/', $timeIn) || !preg_match('/^\d{2}:\d{2}$/', $timeOut) || !$days || $grace === false || $grace < 0 || $break === false || $break < 0 || $hours === false || $hours <= 0 || $hours > 24) {
                adminRedirect('schedules.php', 'danger', 'Enter valid schedule details and select at least one work day.');
            }
            $stmt = $db->prepare("INSERT INTO schedules (schedule_name, time_in, time_out, work_days, grace_period_minutes, break_duration_minutes, required_hours, is_overnight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $timeIn, $timeOut, json_encode($days), $grace, $break, $hours, $overnight]);
            $scheduleId = (int)$db->lastInsertId();
            adminAudit($db, 'Create Schedule', "Created schedule {$name}.", 'schedules', $scheduleId);
            adminRedirect('schedules.php', 'success', 'Schedule created.');
        }

        if ($action === 'assign_schedule') {
            $employee = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
            $schedule = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
            $effectiveDate = (string)($_POST['effective_date'] ?? '');
            $validDate = DateTime::createFromFormat('Y-m-d', $effectiveDate);
            if (!$employee || !$schedule || !$validDate || $validDate->format('Y-m-d') !== $effectiveDate) {
                adminRedirect('schedules.php', 'danger', 'Choose a valid employee, schedule, and effective date.');
            }
            $stmt = $db->prepare("INSERT INTO employee_schedules (employee_id, schedule_id, effective_date) VALUES (?, ?, ?)");
            $stmt->execute([$employee, $schedule, $effectiveDate]);
            adminAudit($db, 'Assign Schedule', "Assigned schedule #{$schedule} to employee #{$employee} from {$effectiveDate}.", 'employee_schedules', (int)$db->lastInsertId());
            adminRedirect('schedules.php', 'success', 'Schedule assigned.');
        }

        adminRedirect('schedules.php', 'danger', 'Unknown schedule action.');
    } catch (Throwable $e) {
        error_log('Schedule update failed: ' . $e->getMessage());
        adminRedirect('schedules.php', 'danger', 'Unable to save schedule. Check that the selected records exist.');
    }
}

$schedules = $db->query("SELECT * FROM schedules ORDER BY schedule_name")->fetchAll();
$employees = $db->query("SELECT id, full_name, employee_code FROM employees WHERE employment_status = 'active' ORDER BY full_name")->fetchAll();
$assignments = $db->query("
    SELECT es.effective_date, e.full_name, e.employee_code, s.schedule_name, s.time_in, s.time_out
    FROM employee_schedules es JOIN employees e ON e.id = es.employee_id
    JOIN schedules s ON s.id = es.schedule_id
    ORDER BY es.effective_date DESC, e.full_name LIMIT 300
")->fetchAll();

adminPageStart('Schedule Management', 'Create work schedules and assign them to employees.');
?>
<div class="card"><div class="card-header"><h3>Create Schedule</h3></div><div class="card-body">
    <form method="post" class="form-grid"><?= csrfInput() ?><input type="hidden" name="action" value="create_schedule">
        <label class="form-group"><span class="form-label">Schedule name</span><input class="form-control" name="schedule_name" maxlength="150" required></label>
        <label class="form-group"><span class="form-label">Time in</span><input class="form-control" type="time" name="time_in" required></label>
        <label class="form-group"><span class="form-label">Time out</span><input class="form-control" type="time" name="time_out" required></label>
        <fieldset class="form-group"><legend class="form-label">Work days</legend><?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?><label class="checkbox-inline"><input type="checkbox" name="work_days[]" value="<?= $day ?>" <?= in_array($day, ['Mon','Tue','Wed','Thu','Fri'], true) ? 'checked' : '' ?>> <?= $day ?></label><?php endforeach; ?></fieldset>
        <label class="form-group"><span class="form-label">Grace minutes</span><input class="form-control" type="number" min="0" max="240" name="grace_period_minutes" value="15" required></label>
        <label class="form-group"><span class="form-label">Break minutes</span><input class="form-control" type="number" min="0" max="480" name="break_duration_minutes" value="60" required></label>
        <label class="form-group"><span class="form-label">Required hours</span><input class="form-control" type="number" min="0.25" max="24" step="0.25" name="required_hours" value="8" required></label>
        <label class="form-group checkbox-inline"><input type="checkbox" name="is_overnight" value="1"> Overnight shift</label>
        <div><button class="btn btn-primary">Create schedule</button></div>
    </form>
</div></div>
<div class="card"><div class="card-header"><h3>Assign Schedule</h3></div><div class="card-body">
<?php if ($employees && $schedules): ?><form method="post" class="form-inline"><?= csrfInput() ?><input type="hidden" name="action" value="assign_schedule">
    <label class="form-label">Employee <select class="form-control" name="employee_id" required><?php foreach ($employees as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= adminEscape($employee['full_name'] . ' (' . $employee['employee_code'] . ')') ?></option><?php endforeach; ?></select></label>
    <label class="form-label">Schedule <select class="form-control" name="schedule_id" required><?php foreach ($schedules as $schedule): ?><option value="<?= (int)$schedule['id'] ?>"><?= adminEscape($schedule['schedule_name']) ?></option><?php endforeach; ?></select></label>
    <label class="form-label">Effective date <input class="form-control" type="date" name="effective_date" value="<?= date('Y-m-d') ?>" required></label><button class="btn btn-primary">Assign</button>
</form><?php else: ?><p>Add an active employee and a schedule before making assignments.</p><?php endif; ?>
</div></div>
<div class="card"><div class="card-header"><h3>Schedules</h3></div><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Hours</th><th>Work days</th><th>Grace / Break</th><th>Required</th></tr></thead><tbody>
<?php foreach ($schedules as $schedule): ?><tr><td><?= adminEscape($schedule['schedule_name']) ?><br><?= adminEscape(date('g:i A', strtotime($schedule['time_in']))) ?> – <?= adminEscape(date('g:i A', strtotime($schedule['time_out']))) ?></td><td><?= adminEscape($schedule['is_overnight'] ? 'Overnight' : 'Day shift') ?></td><td><?= adminEscape(implode(', ', json_decode($schedule['work_days'], true) ?: [])) ?></td><td><?= (int)$schedule['grace_period_minutes'] ?> min / <?= (int)$schedule['break_duration_minutes'] ?> min</td><td><?= number_format((float)$schedule['required_hours'], 2) ?> h</td></tr><?php endforeach; ?>
<?php if (!$schedules): ?><tr><td colspan="5">No schedules configured.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="card"><div class="card-header"><h3>Recent Assignments</h3></div><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Employee</th><th>Schedule</th><th>Effective</th></tr></thead><tbody>
<?php foreach ($assignments as $assignment): ?><tr><td><?= adminEscape($assignment['full_name']) ?><br><?= adminEscape($assignment['employee_code']) ?></td><td><?= adminEscape($assignment['schedule_name']) ?><br><?= adminEscape(date('g:i A', strtotime($assignment['time_in'])) . ' – ' . date('g:i A', strtotime($assignment['time_out']))) ?></td><td><?= adminEscape(date('M j, Y', strtotime($assignment['effective_date']))) ?></td></tr><?php endforeach; ?>
<?php if (!$assignments): ?><tr><td colspan="3">No schedule assignments yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php adminPageEnd(); ?>
