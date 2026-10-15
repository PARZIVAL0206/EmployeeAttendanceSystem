<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/includes/icons.php';

// ── Mode detection ──────────────────────────────────────────────
// Mode A: ?ref=ID  → look up that specific registration
// Mode B: no ref   → show email lookup form (or result after POST)

$registration = null;     // single record (Mode A)
$allByEmail   = [];       // all records for an email (Mode B POST result)
$lookupEmail  = '';
$lookupError  = '';

// ── Mode A: direct reference ID in URL ─────────────────────────
$refId = isset($_GET['ref']) ? (int)$_GET['ref'] : 0;

if ($refId > 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT r.*, d.name AS department_name,
                   e.employee_code,
                   u.username AS account_username
            FROM registrations r
            LEFT JOIN departments d  ON r.department_id = d.id
            LEFT JOIN employees   e  ON e.registration_id = r.id
            LEFT JOIN users       u  ON e.id             = u.employee_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$refId]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lookupError = 'Unable to retrieve registration. Please try again.';
        error_log('Registration status lookup failed: ' . $e->getMessage());
    }
}

// ── Mode B: email lookup form (POST) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $refId === 0) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $lookupError = 'Invalid security token. Please try again.';
    } else {
        $lookupEmail = sanitize($_POST['email'] ?? '');
        if ($lookupEmail === '' || !filter_var($lookupEmail, FILTER_VALIDATE_EMAIL)) {
            $lookupError = 'Please enter a valid email address.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    SELECT r.*, d.name AS department_name,
                           e.employee_code, u.username AS account_username
                    FROM registrations r
                    LEFT JOIN departments d  ON r.department_id = d.id
                    LEFT JOIN employees   e  ON e.registration_id = r.id
                    LEFT JOIN users       u  ON e.id             = u.employee_id
                    WHERE r.email = ?
                    ORDER BY r.applied_at DESC
                ");
                $stmt->execute([$lookupEmail]);
                $allByEmail = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($allByEmail)) {
                    $lookupError = 'No application found for that email address.';
                }
            } catch (Exception $e) {
                $lookupError = 'Unable to retrieve applications. Please try again.';
                error_log('Application email lookup failed: ' . $e->getMessage());
            }
        }
    }
}

// ── Helper: status badge HTML ────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'pending'  => ['#fff3cd', '#856404', 'Pending Review'],
        'approved' => ['#d1e7dd', '#0f5132', 'Approved'],
        'rejected' => ['#f8d7da', '#842029', 'Rejected'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#e2e3e5', '#41464b', ucfirst($status)];
    return "<span style=\"background:{$bg};color:{$color};padding:0.25rem 0.8rem;
            border-radius:50px;font-size:0.8rem;font-weight:700;\">{$label}</span>";
}

$pageTitle = 'Application Status';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - OnTime: A Web-Based Attendance Management System for AICS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        body {
            background: linear-gradient(135deg, #e3f0fb 0%, #f0f6ff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
            padding: 2rem 1rem;
        }
        .status-wrapper { max-width: 620px; margin: 0 auto; }
        .status-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(30,100,180,0.13);
            padding: 2.5rem 2rem;
            margin-bottom: 1.5rem;
        }
        .card-header { text-align: center; margin-bottom: 1.8rem; }
        .card-header .logo-icon {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 0.6rem;
        }
        .card-header .logo-icon svg { width: 32px; height: 32px; fill: #fff; }
        .card-header h1 { font-size: 1.3rem; font-weight: 700; color: #1a237e; margin: 0 0 0.15rem; }
        .card-header p  { font-size: 0.82rem; color: #5c6b8a; margin: 0; }

        /* Lookup form */
        .form-group { margin-bottom: 1.1rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #344055; margin-bottom: 0.35rem; }
        .form-group input[type="email"] {
            width: 100%; padding: 0.6rem 0.85rem;
            border: 1.5px solid #c5d4ea; border-radius: 8px;
            font-size: 0.93rem; background: #f7faff; box-sizing: border-box;
        }
        .form-group input:focus { outline: none; border-color: #1a73e8; background: #fff; }
        .btn-lookup {
            width: 100%; padding: 0.72rem;
            background: linear-gradient(90deg, #1a73e8, #0d47a1);
            color: #fff; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            letter-spacing: 0.03em; transition: opacity 0.2s;
        }
        .btn-lookup:hover { opacity: 0.88; }

        /* Status result card */
        .result-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 18px rgba(30,100,180,0.10);
            padding: 1.5rem; margin-bottom: 1rem;
            border-left: 5px solid #1a73e8;
        }
        .result-card.status-pending  { border-left-color: #ffc107; }
        .result-card.status-approved { border-left-color: #28a745; }
        .result-card.status-rejected { border-left-color: #dc3545; }
        .result-meta { font-size: 0.82rem; color: #5c6b8a; margin-bottom: 1rem; }
        .result-name { font-size: 1.1rem; font-weight: 700; color: #1a237e; margin-bottom: 0.4rem; }
        .info-row { display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; margin: 0.8rem 0; }
        .info-item { font-size: 0.85rem; }
        .info-item .label { font-weight: 600; color: #344055; }
        .info-item .value { color: #1a237e; }
        .welcome-box {
            background: #e8f0fe; border-radius: 8px; padding: 0.8rem 1rem;
            margin-top: 0.8rem; font-size: 0.88rem; color: #1a237e;
        }
        .rejection-box {
            background: #fde8e8; border-radius: 8px; padding: 0.8rem 1rem;
            margin-top: 0.8rem; font-size: 0.88rem; color: #842029;
        }
        .alert { padding: 0.65rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.87rem; }
        .alert-danger   { background: #fde8e8; color: #c0392b; border: 1px solid #f5c2c2; }
        .alert-success  { background: #e6f4ea; color: #1e6e34; border: 1px solid #b7dfbf; }
        .bottom-links { text-align: center; margin-top: 1rem; font-size: 0.85rem; color: #5c6b8a; }
        .bottom-links a { color: #1a73e8; text-decoration: none; font-weight: 600; }
        .bottom-links a:hover { text-decoration: underline; }
        .ref-number { font-size: 0.78rem; color: #8899aa; }
    </style>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="status-wrapper">
    <div class="status-card">

        <!-- Header -->
        <div class="card-header">
            <div class="logo-icon">
                <?= uiIcon('calendar-check') ?>
            </div>
            <div class="brand-title">OnTime</div>
            <p class="brand-tagline">A Web-Based Attendance Management System for AICS</p>
            <h1>Application Status</h1>
            <p>Track your employee registration application</p>
        </div>

        <!-- Flash messages -->
        <?php displayFlashMessage(); ?>

        <!-- ── MODE A: Direct ref ID lookup ── -->
        <?php if ($refId > 0): ?>
            <?php if ($lookupError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($lookupError) ?></div>
            <?php elseif (!$registration): ?>
                <div class="alert alert-danger">No application found with reference #<?= $refId ?>.</div>
            <?php else: ?>
                <?php $status = $registration['status']; ?>
                <div class="result-card status-<?= htmlspecialchars($status) ?>">
                    <div class="result-meta">
                        Reference #<?= (int)$registration['id'] ?> &nbsp;&bull;&nbsp;
                        Applied: <?= htmlspecialchars(formatDateTime($registration['applied_at'])) ?>
                        &nbsp;&bull;&nbsp; <?= statusBadge($status) ?>
                    </div>
                    <div class="result-name">
                        <?= htmlspecialchars($registration['honorific'] . ' ' . $registration['full_name']) ?>
                    </div>

                    <div class="info-row">
                        <div class="info-item">
                            <span class="label">Type: </span>
                            <span class="value"><?= htmlspecialchars($registration['employee_type']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Department: </span>
                            <span class="value"><?= htmlspecialchars($registration['department_name'] ?? '—') ?></span>
                        </div>
                        <?php if ($registration['employee_type'] === 'Instructor' && $registration['specialization']): ?>
                        <div class="info-item">
                            <span class="label">Specialization: </span>
                            <span class="value"><?= htmlspecialchars($registration['specialization']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($status === 'pending'): ?>
                        <div class="welcome-box" style="background:#fff8e1;color:#795548;">
                            <?= uiIcon('hourglass') ?> <strong>Your application is under review.</strong>
                            Please wait for administrator approval. You will be notified by email once a decision is made.
                        </div>

                    <?php elseif ($status === 'approved'): ?>
                        <div class="info-row">
                            <?php if ($registration['employee_code']): ?>
                            <div class="info-item">
                                <span class="label">Employee ID: </span>
                                <span class="value"><?= htmlspecialchars($registration['employee_code']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($registration['account_username']): ?>
                            <div class="info-item">
                                <span class="label">Username: </span>
                                <span class="value"><?= htmlspecialchars($registration['account_username']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="welcome-box">
                            <?= uiIcon('check-circle') ?> <strong>Congratulations! Your application has been approved.</strong>
                            You may now log in using your assigned username and the password you registered with.
                            <br><br>
                            <a href="<?= htmlspecialchars(appUrl('login.php'), ENT_QUOTES, 'UTF-8') ?>" style="color:#1a73e8;font-weight:700;">
                                <?= uiIcon('arrow-right') ?> Go to Login Page
                            </a>
                        </div>

                    <?php elseif ($status === 'rejected'): ?>
                        <div class="rejection-box">
                            <?= uiIcon('x-circle') ?> <strong>Your application was not approved.</strong>
                            <?php if (!empty($registration['rejection_reason'])): ?>
                                <br><br><strong>Reason: </strong>
                                <?= htmlspecialchars($registration['rejection_reason']) ?>
                            <?php endif; ?>
                            <br><br>
                            If you believe this is an error, please contact the school administration.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <!-- ── MODE B: Email lookup form ── -->
        <?php else: ?>
            <!-- Error from lookup -->
            <?php if ($lookupError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($lookupError) ?></div>
            <?php endif; ?>

            <!-- Results for looked-up email -->
            <?php if (!empty($allByEmail)): ?>
                <p style="font-size:0.88rem;color:#5c6b8a;margin-bottom:1rem;">
                    Found <?= count($allByEmail) ?> application(s) for
                    <strong><?= htmlspecialchars($lookupEmail) ?></strong>:
                </p>
                <?php foreach ($allByEmail as $reg): ?>
                    <?php $status = $reg['status']; ?>
                    <div class="result-card status-<?= htmlspecialchars($status) ?>">
                        <div class="result-meta">
                            Reference #<?= (int)$reg['id'] ?> &nbsp;&bull;&nbsp;
                            Applied: <?= htmlspecialchars(formatDateTime($reg['applied_at'])) ?>
                            &nbsp;&bull;&nbsp; <?= statusBadge($status) ?>
                        </div>
                        <div class="result-name">
                            <?= htmlspecialchars($reg['honorific'] . ' ' . $reg['full_name']) ?>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <span class="label">Type: </span>
                                <span class="value"><?= htmlspecialchars($reg['employee_type']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Department: </span>
                                <span class="value"><?= htmlspecialchars($reg['department_name'] ?? '—') ?></span>
                            </div>
                        </div>

                        <?php if ($status === 'pending'): ?>
                            <div class="welcome-box" style="background:#fff8e1;color:#795548;">
                                <?= uiIcon('hourglass') ?> Your application is under review. Please wait for administrator approval.
                            </div>

                        <?php elseif ($status === 'approved'): ?>
                            <div class="info-row">
                                <?php if ($reg['employee_code']): ?>
                                <div class="info-item">
                                    <span class="label">Employee ID: </span>
                                    <span class="value"><?= htmlspecialchars($reg['employee_code']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($reg['account_username']): ?>
                                <div class="info-item">
                                    <span class="label">Username: </span>
                                    <span class="value"><?= htmlspecialchars($reg['account_username']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="welcome-box">
                                <?= uiIcon('check-circle') ?> <strong>Application approved!</strong>
                                Log in with your username and the password you registered with.
                                <a href="<?= htmlspecialchars(appUrl('login.php'), ENT_QUOTES, 'UTF-8') ?>" style="color:#1a73e8;font-weight:700;display:block;margin-top:0.5rem;">
                                    <?= uiIcon('arrow-right') ?> Go to Login Page
                                </a>
                            </div>

                        <?php elseif ($status === 'rejected'): ?>
                            <div class="rejection-box">
                                <?= uiIcon('x-circle') ?> <strong>Application not approved.</strong>
                                <?php if (!empty($reg['rejection_reason'])): ?>
                                    <br>Reason: <?= htmlspecialchars($reg['rejection_reason']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Email lookup form -->
            <p style="font-size:0.88rem;color:#344055;margin-bottom:1rem;">
                Enter the email address you used during registration to check your application status.
            </p>
            <form method="POST" action="">
                <?= csrfInput() ?>
                <div class="form-group">
                    <label for="email">Registered Email Address</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($lookupEmail) ?>"
                           placeholder="example@school.edu.ph" required>
                </div>
                <button type="submit" class="btn-lookup"><?= uiIcon('search') ?> Check Status</button>
            </form>
        <?php endif; ?>

        <div class="bottom-links">
            <a href="<?= htmlspecialchars(appUrl('login.php'), ENT_QUOTES, 'UTF-8') ?>"><?= uiIcon('arrow-left') ?> Back to Login</a>
            &nbsp;|&nbsp;
            <a href="<?= htmlspecialchars(appUrl('register.php'), ENT_QUOTES, 'UTF-8') ?>">New Application</a>
        </div>
    </div>
</div>
</body>
</html>
