<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/includes/icons.php';

// Redirect already-logged-in users to their dashboard
redirectIfLoggedIn();

$error   = '';
$success = '';

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // 2. Sanitize username
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $db = getDB();
                // 3. Fetch user with employee info
                $stmt = $db->prepare("
                    SELECT users.*, employees.full_name, employees.employee_code
                    FROM users
                    LEFT JOIN employees ON users.employee_id = employees.id
                    WHERE users.username = ?
                    LIMIT 1
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    // Not found at all
                    $error = 'Invalid username or password.';
                } elseif ($user['status'] === 'inactive') {
                    $error = 'Your account has been deactivated.';
                } elseif ($user['status'] === 'pending') {
                    $error = 'Your account is pending approval.';
                } elseif ($user['status'] !== 'active') {
                    $error = 'Invalid username or password.';
                } elseif (!password_verify($password, $user['password'])) {
                    $error = 'Invalid username or password.';
                } else {
                    // 4. Successful login — set session
                    session_regenerate_id(true);
                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['username']      = $user['username'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['full_name']     = $user['full_name'] ?? $user['username'];
                    $_SESSION['employee_id']   = $user['employee_id'] ?? null;
                    $_SESSION['employee_code'] = $user['employee_code'] ?? null;

                    // Log the login event
                    logAudit($db, (int)$user['id'], 'Login', 'User logged in', 'users', (int)$user['id']);

                    // 5. Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: ' . appUrl('admin/dashboard.php'));
                    } else {
                        header('Location: ' . appUrl('employee/dashboard.php'));
                    }
                    exit;
                }
            } catch (Exception $e) {
                $error = 'A system error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - OnTime: A Web-Based Attendance Management System for AICS</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        /* ── Login page layout ── */
        body {
            background: linear-gradient(135deg, #e3f0fb 0%, #f0f6ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(30, 100, 180, 0.13);
            padding: 2.5rem 2rem 2rem;
        }
        .school-logo-area {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .school-logo-area .logo-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }
        .school-logo-area .logo-icon svg {
            width: 38px;
            height: 38px;
            fill: #fff;
        }
        .school-logo-area p {
            font-size: 0.82rem;
            color: #5c6b8a;
            margin: 0 auto;
            max-width: 300px;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 1.1rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #344055;
            margin-bottom: 0.35rem;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1.5px solid #c5d4ea;
            border-radius: 8px;
            font-size: 0.95rem;
            background: #f7faff;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1a73e8;
            background: #fff;
        }
        .role-badge {
            display: inline-block;
            margin-top: 0.35rem;
            padding: 0.18rem 0.7rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .role-badge.admin    { background: #e8f0fe; color: #1a73e8; }
        .role-badge.employee { background: #e6f4ea; color: #1e7e34; }
        .role-badge.pending  { background: #fff3cd; color: #856404; }
        .role-badge.inactive { background: #fde8e8; color: #c0392b; }
        .btn-login {
            width: 100%;
            padding: 0.72rem;
            background: linear-gradient(90deg, #1a73e8, #0d47a1);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: opacity 0.2s;
            margin-top: 0.4rem;
        }
        .btn-login:hover { opacity: 0.88; }
        .login-links {
            text-align: center;
            margin-top: 1.2rem;
            font-size: 0.85rem;
            color: #5c6b8a;
        }
        .login-links a {
            color: #1a73e8;
            text-decoration: none;
            font-weight: 600;
        }
        .login-links a:hover { text-decoration: underline; }
        .login-links .sep { margin: 0 0.5rem; color: #bcc8dc; }
        .alert {
            padding: 0.65rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.87rem;
            font-weight: 500;
        }
        .alert-danger  { background: #fde8e8; color: #c0392b; border: 1px solid #f5c2c2; }
        .alert-success { background: #e6f4ea; color: #1e6e34; border: 1px solid #b7dfbf; }
        .role-hint-wrap { min-height: 1.5rem; }
    </style>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">

        <!-- School logo / branding area -->
        <div class="school-logo-area">
            <div class="logo-icon">
                <!-- Graduation cap icon -->
                <?= uiIcon('calendar-check') ?>
            </div>
            <h1 class="brand-title">OnTime</h1>
            <p>A Web-Based Attendance Management System for AICS</p>
        </div>

        <!-- Flash messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php displayFlashMessage(); ?>

        <!-- Login form -->
        <form method="POST" action="" id="loginForm" novalidate>
            <?= csrfInput() ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    placeholder="Enter your username"
                    autocomplete="username"
                    required
                >
                <!-- Role badge injected by JS after blur -->
                <div class="role-hint-wrap" id="roleHintWrap"></div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="login-links">
            Don't have an account?
            <a href="<?= htmlspecialchars(appUrl('register.php'), ENT_QUOTES, 'UTF-8') ?>">Register here</a>
            <span class="sep">|</span>
            <a href="<?= htmlspecialchars(appUrl('status.php'), ENT_QUOTES, 'UTF-8') ?>">Check application status</a>
        </div>
    </div>
</div>

<script>
(function () {
    const usernameInput = document.getElementById('username');
    const roleHintWrap  = document.getElementById('roleHintWrap');
    let lastChecked = '';

    usernameInput.addEventListener('blur', function () {
        const val = this.value.trim();
        if (val === '' || val === lastChecked) return;
        lastChecked = val;
        roleHintWrap.innerHTML = '';

        fetch(<?= json_encode(appUrl('api/check_username.php')) ?> + '?username=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
                if (!data.found) return;
                let cls = 'employee';
                if (data.role === 'Administrator') cls = 'admin';
                if (data.status === 'pending')  cls = 'pending';
                if (data.status === 'inactive') cls = 'inactive';

                let label = data.role;
                if (data.status === 'pending')  label += ' (Pending Approval)';
                if (data.status === 'inactive') label += ' (Deactivated)';

                roleHintWrap.innerHTML =
                    '<span class="role-badge ' + cls + '">' + label + '</span>';
            })
            .catch(() => {}); // Silently ignore network errors
    });
})();
</script>
</body>
</html>
