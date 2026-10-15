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
<section class="schedule-banner">
    <div>
        <p class="schedule-kicker"><?= uiIcon('calendar') ?> Workforce planning</p>
        <h2>Build a better rhythm for your team.</h2>
        <p>Define reliable shifts, then assign them with a clear effective date.</p>
    </div>
    <div class="schedule-banner-mark" aria-hidden="true">
        <span><?= uiIcon('clock') ?></span>
        <small>SHIFT PLANNER</small>
    </div>
</section>

<section class="schedule-summary" aria-label="Schedule overview">
    <article class="schedule-summary-item">
        <span class="schedule-summary-icon"><?= uiIcon('calendar-check') ?></span>
        <div><strong><?= count($schedules) ?></strong><span>Saved schedules</span></div>
    </article>
    <article class="schedule-summary-item">
        <span class="schedule-summary-icon"><?= uiIcon('users') ?></span>
        <div><strong><?= count($employees) ?></strong><span>Active employees</span></div>
    </article>
    <article class="schedule-summary-item">
        <span class="schedule-summary-icon"><?= uiIcon('clipboard') ?></span>
        <div><strong><?= count($assignments) ?></strong><span>Recent assignments</span></div>
    </article>
</section>

<div class="schedule-workspace">
    <section class="card schedule-panel schedule-builder" aria-labelledby="schedule-create-title">
        <div class="schedule-panel-heading">
            <div>
                <p class="schedule-kicker">01 / DEFINE</p>
                <h2 id="schedule-create-title">Create a schedule</h2>
                <p>Set the hours, working days, and attendance rules.</p>
            </div>
            <span class="schedule-heading-icon"><?= uiIcon('calendar-check') ?></span>
        </div>

        <form method="post" class="schedule-form">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="create_schedule">

            <label class="form-group schedule-form-wide">
                <span class="form-label">Schedule name</span>
                <input class="form-control" name="schedule_name" maxlength="150" placeholder="e.g. Morning shift" required>
            </label>

            <label class="form-group">
                <span class="form-label">Time in</span>
                <input class="form-control" type="time" name="time_in" required>
            </label>
            <label class="form-group">
                <span class="form-label">Time out</span>
                <input class="form-control" type="time" name="time_out" required>
            </label>

            <fieldset class="schedule-days schedule-form-wide">
                <legend class="form-label">Work days</legend>
                <div class="schedule-day-options">
                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                    <label class="schedule-day-choice">
                        <input type="checkbox" name="work_days[]" value="<?= $day ?>" <?= in_array($day, ['Mon','Tue','Wed','Thu','Fri'], true) ? 'checked' : '' ?>>
                        <span><?= $day ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="schedule-rule-grid schedule-form-wide">
                <label class="form-group">
                    <span class="form-label">Grace period</span>
                    <span class="schedule-input-with-unit"><input class="form-control" type="number" min="0" max="240" name="grace_period_minutes" value="15" required><span>min</span></span>
                </label>
                <label class="form-group">
                    <span class="form-label">Break duration</span>
                    <span class="schedule-input-with-unit"><input class="form-control" type="number" min="0" max="480" name="break_duration_minutes" value="60" required><span>min</span></span>
                </label>
                <label class="form-group">
                    <span class="form-label">Required hours</span>
                    <span class="schedule-input-with-unit"><input class="form-control" type="number" min="0.25" max="24" step="0.25" name="required_hours" value="8" required><span>hrs</span></span>
                </label>
            </div>

            <div class="schedule-form-footer schedule-form-wide">
                <label class="schedule-overnight">
                    <input type="checkbox" name="is_overnight" value="1">
                    <span class="schedule-checkbox-mark"></span>
                    <span><strong>Overnight shift</strong><small>Ends on the following day</small></span>
                </label>
                <button class="btn btn-primary" type="submit"><?= uiIcon('calendar-check') ?> Create schedule</button>
            </div>
        </form>
    </section>

    <section class="card schedule-panel schedule-assigner" aria-labelledby="schedule-assign-title">
        <div class="schedule-panel-heading">
            <div>
                <p class="schedule-kicker">02 / ASSIGN</p>
                <h2 id="schedule-assign-title">Assign a shift</h2>
                <p>Choose an employee and when the schedule takes effect.</p>
            </div>
            <span class="schedule-heading-icon"><?= uiIcon('users') ?></span>
        </div>

        <?php if ($employees && $schedules): ?>
        <form method="post" class="schedule-assign-form">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="assign_schedule">
            <label class="form-group">
                <span class="form-label">Employee</span>
                <select class="form-control" name="employee_id" required>
                    <?php foreach ($employees as $employee): ?>
                    <option value="<?= (int)$employee['id'] ?>"><?= adminEscape($employee['full_name'] . ' (' . $employee['employee_code'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-group">
                <span class="form-label">Schedule</span>
                <select class="form-control" name="schedule_id" required>
                    <?php foreach ($schedules as $schedule): ?>
                    <option value="<?= (int)$schedule['id'] ?>"><?= adminEscape($schedule['schedule_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-group">
                <span class="form-label">Effective date</span>
                <input class="form-control" type="date" name="effective_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <button class="btn btn-primary" type="submit"><?= uiIcon('check') ?> Assign schedule</button>
        </form>
        <?php else: ?>
        <div class="schedule-empty-state">
            <span><?= uiIcon('clipboard') ?></span>
            <strong>Ready when you are</strong>
            <p><?= !$schedules ? 'Create a schedule first' : 'Add an active employee first' ?>, then you can assign shifts here.</p>
        </div>
        <?php endif; ?>
    </section>
</div>

<section class="card schedule-panel schedule-library" aria-labelledby="schedule-library-title">
    <div class="schedule-section-heading">
        <div>
            <p class="schedule-kicker">THE LIBRARY</p>
            <h2 id="schedule-library-title">Saved schedules</h2>
        </div>
        <span class="schedule-count"><?= count($schedules) ?> total</span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped schedule-table">
            <thead><tr><th>Schedule</th><th>Work days</th><th>Shift</th><th>Grace / break</th><th>Required</th></tr></thead>
            <tbody>
            <?php foreach ($schedules as $schedule): ?>
                <?php $workDays = json_decode($schedule['work_days'], true) ?: []; ?>
                <tr>
                    <td>
                        <div class="schedule-name-cell">
                            <span class="schedule-row-icon"><?= uiIcon('clock') ?></span>
                            <span><strong><?= adminEscape($schedule['schedule_name']) ?></strong><small><?= adminEscape(date('g:i A', strtotime($schedule['time_in'])) . ' - ' . date('g:i A', strtotime($schedule['time_out']))) ?></small></span>
                        </div>
                    </td>
                    <td><div class="schedule-day-list"><?php foreach ($workDays as $day): ?><span><?= adminEscape($day) ?></span><?php endforeach; ?></div></td>
                    <td><span class="schedule-shift-tag <?= $schedule['is_overnight'] ? 'is-overnight' : '' ?>"><?= adminEscape($schedule['is_overnight'] ? 'Overnight' : 'Day shift') ?></span></td>
                    <td><?= (int)$schedule['grace_period_minutes'] ?> min <span class="schedule-cell-muted">/</span> <?= (int)$schedule['break_duration_minutes'] ?> min</td>
                    <td><strong><?= number_format((float)$schedule['required_hours'], 2) ?> h</strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$schedules): ?><tr><td colspan="5"><div class="schedule-table-empty"><?= uiIcon('calendar') ?> No schedules yet. Create your first shift above.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card schedule-panel schedule-assignments" aria-labelledby="schedule-assignment-title">
    <div class="schedule-section-heading">
        <div>
            <p class="schedule-kicker">TEAM ROTATION</p>
            <h2 id="schedule-assignment-title">Recent assignments</h2>
        </div>
        <span class="schedule-count">Latest <?= count($assignments) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-striped schedule-table assignment-table">
            <thead><tr><th>Employee</th><th>Assigned schedule</th><th>Effective date</th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td><strong><?= adminEscape($assignment['full_name']) ?></strong><small class="schedule-subcell"><?= adminEscape($assignment['employee_code']) ?></small></td>
                    <td><strong><?= adminEscape($assignment['schedule_name']) ?></strong><small class="schedule-subcell"><?= adminEscape(date('g:i A', strtotime($assignment['time_in'])) . ' - ' . date('g:i A', strtotime($assignment['time_out']))) ?></small></td>
                    <td><time datetime="<?= adminEscape($assignment['effective_date']) ?>"><?= adminEscape(date('M j, Y', strtotime($assignment['effective_date']))) ?></time></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assignments): ?><tr><td colspan="3"><div class="schedule-table-empty"><?= uiIcon('users') ?> No employee assignments yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php adminPageEnd(); ?>
