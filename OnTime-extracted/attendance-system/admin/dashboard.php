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
<section class="admin-dashboard-hero">
    <div class="admin-hero-copy">
        <div class="admin-hero-meta">
            <span><i aria-hidden="true"></i> DAILY BRIEF</span>
            <time datetime="<?= adminEscape($today) ?>"><?= adminEscape(date('l, F j')) ?></time>
        </div>
        <h2>People present.<br>Plans in motion.</h2>
        <p>A clear view of attendance and the work waiting for review.</p>
        <a class="admin-hero-link" href="<?= adminEscape(appUrl('admin/attendance.php')) ?>">
            Open attendance monitor <?= uiIcon('arrow-right') ?>
        </a>
    </div>
    <div class="admin-hero-time" aria-label="Current local time">
        <span>LOCAL TIME</span>
        <strong><?= adminEscape(date('g:i')) ?></strong>
        <small><?= adminEscape(date('A')) ?> <span><?= uiIcon('clock') ?></span></small>
    </div>
</section>

<section class="admin-overview-grid" aria-label="Today's overview">
    <a class="admin-overview-card overview-people" href="<?= adminEscape(appUrl('admin/employees.php')) ?>">
        <span class="overview-icon"><?= uiIcon('users') ?></span>
        <span class="overview-copy"><span>ACTIVE EMPLOYEES</span><strong><?= $stats['employees'] ?></strong><small>View employee directory <?= uiIcon('arrow-right') ?></small></span>
        <span class="overview-index">01</span>
    </a>
    <a class="admin-overview-card overview-attendance" href="<?= adminEscape(appUrl('admin/attendance.php')) ?>">
        <span class="overview-icon"><?= uiIcon('clock') ?></span>
        <span class="overview-copy"><span>CHECKED IN TODAY</span><strong><?= $stats['present'] ?></strong><small>Monitor attendance <?= uiIcon('arrow-right') ?></small></span>
        <span class="overview-index">02</span>
    </a>
    <a class="admin-overview-card overview-registrations" href="<?= adminEscape(appUrl('admin/registrations.php')) ?>">
        <span class="overview-icon"><?= uiIcon('clipboard') ?></span>
        <span class="overview-copy"><span>APPLICATIONS TO REVIEW</span><strong><?= $stats['pending_registrations'] ?></strong><small>Open registrations <?= uiIcon('arrow-right') ?></small></span>
        <span class="overview-index">03</span>
    </a>
    <a class="admin-overview-card overview-leaves" href="<?= adminEscape(appUrl('admin/leaves.php')) ?>">
        <span class="overview-icon"><?= uiIcon('calendar') ?></span>
        <span class="overview-copy"><span>LEAVE REQUESTS</span><strong><?= $stats['pending_leaves'] ?></strong><small>Review requests <?= uiIcon('arrow-right') ?></small></span>
        <span class="overview-index">04</span>
    </a>
</section>

<div class="admin-dashboard-grid">
    <section class="card admin-dashboard-panel" aria-labelledby="attendance-panel-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-panel-kicker">LIVE ROSTER</p>
                <h2 id="attendance-panel-title">Today's attendance</h2>
                <p>Latest employee check-ins for <?= adminEscape(date('F j, Y')) ?>.</p>
            </div>
            <a class="dashboard-panel-link" href="<?= adminEscape(appUrl('admin/attendance.php')) ?>">
                View all <?= uiIcon('arrow-right') ?>
            </a>
        </div>

        <?php if ($recentAttendance): ?>
        <div class="table-responsive">
            <table class="table table-striped admin-dashboard-table">
                <thead><tr><th>Employee</th><th>Time in</th><th>Time out</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentAttendance as $row): ?>
                    <?php $statusSlug = strtolower((string)$row['attendance_status']); ?>
                    <tr>
                        <td>
                            <div class="dashboard-employee">
                                <span><?= adminEscape(strtoupper(substr($row['full_name'], 0, 1))) ?></span>
                                <div><strong><?= adminEscape($row['full_name']) ?></strong><small><?= adminEscape($row['employee_code']) ?></small></div>
                            </div>
                        </td>
                        <td><?= $row['actual_time_in'] ? adminEscape(date('g:i A', strtotime($row['actual_time_in']))) : '—' ?></td>
                        <td><?= $row['actual_time_out'] ? adminEscape(date('g:i A', strtotime($row['actual_time_out']))) : '—' ?></td>
                        <td><span class="attendance-status-tag status-<?= adminEscape($statusSlug) ?>"><?= adminEscape(ucwords(str_replace('_', ' ', $statusSlug))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="dashboard-empty-state">
            <span><?= uiIcon('clock') ?></span>
            <strong>No check-ins yet</strong>
            <p>Today's attendance records will appear here as employees clock in.</p>
        </div>
        <?php endif; ?>
    </section>

    <aside class="card admin-action-panel" aria-labelledby="action-panel-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-panel-kicker">NEXT UP</p>
                <h2 id="action-panel-title">Needs your attention</h2>
            </div>
        </div>
        <a class="admin-action-row" href="<?= adminEscape(appUrl('admin/registrations.php')) ?>">
            <span class="admin-action-icon action-registration"><?= uiIcon('clipboard') ?></span>
            <span class="admin-action-copy"><strong>Applications</strong><small>Review new employee requests</small></span>
            <span class="admin-action-count"><?= $stats['pending_registrations'] ?></span>
            <?= uiIcon('arrow-right') ?>
        </a>
        <a class="admin-action-row" href="<?= adminEscape(appUrl('admin/leaves.php')) ?>">
            <span class="admin-action-icon action-leave"><?= uiIcon('calendar') ?></span>
            <span class="admin-action-copy"><strong>Leave requests</strong><small>Review pending time off</small></span>
            <span class="admin-action-count"><?= $stats['pending_leaves'] ?></span>
            <?= uiIcon('arrow-right') ?>
        </a>
        <div class="admin-action-note">
            <span><?= uiIcon('check-circle') ?></span>
            <p>Keep approvals up to date so schedules stay accurate.</p>
        </div>
        <a class="admin-shortcut-link" href="<?= adminEscape(appUrl('admin/schedules.php')) ?>">
            Go to schedule management <?= uiIcon('arrow-right') ?>
        </a>
    </aside>
</div>
<?php adminPageEnd(); ?>
