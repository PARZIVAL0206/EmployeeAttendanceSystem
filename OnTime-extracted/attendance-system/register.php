<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/includes/icons.php';

// Redirect already-logged-in users
redirectIfLoggedIn();

$errors      = [];
$formData    = [];
$departments = [];

// Load departments from DB for the select dropdown
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Non-fatal; dropdown will be empty
}

// Handle POST registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        // 2. Collect and sanitize fields
        $formData = [
            'full_name'        => sanitize($_POST['full_name'] ?? ''),
            'honorific'        => sanitize($_POST['honorific'] ?? ''),
            'age'              => (int)($_POST['age'] ?? 0),
            'gender'           => sanitize($_POST['gender'] ?? ''),
            'email'            => sanitize($_POST['email'] ?? ''),
            'contact_number'   => sanitize($_POST['contact_number'] ?? ''),
            'employee_type'    => sanitize($_POST['employee_type'] ?? ''),
            'department_id'    => (int)($_POST['department_id'] ?? 0),
            'specialization'   => sanitize($_POST['specialization'] ?? ''),
            'password'         => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
        ];

        // 3. Validate required fields
        if ($formData['full_name'] === '')      $errors[] = 'Full name is required.';
        if ($formData['honorific'] === '')      $errors[] = 'Honorific is required.';
        if ($formData['age'] < 18 || $formData['age'] > 80) $errors[] = 'Age must be between 18 and 80.';
        if ($formData['gender'] === '')         $errors[] = 'Gender is required.';
        if ($formData['email'] === '')          $errors[] = 'Email address is required.';
        if ($formData['contact_number'] === '') $errors[] = 'Contact number is required.';
        if ($formData['employee_type'] === '')  $errors[] = 'Employee type is required.';
        if ($formData['department_id'] === 0)   $errors[] = 'Department is required.';
        if ($formData['password'] === '')       $errors[] = 'Password is required.';

        // 4. Validate email format
        if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        // 5. Password length and match
        if (strlen($formData['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($formData['password'] !== $formData['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }

        // 6. Check employee_type is valid
        if (!in_array($formData['employee_type'], ['instructor', 'staff'], true)) {
            $errors[] = 'Please select a valid employee type.';
        }

        // 7. Instructor requires specialization
        if ($formData['employee_type'] === 'instructor' && $formData['specialization'] === '') {
            $errors[] = 'Field of Specialization is required for Instructors.';
        }

        // 8. Check email uniqueness in registrations and employees tables
        if (empty($errors) && $formData['email'] !== '') {
            try {
                $stmt = $db->prepare("SELECT id FROM registrations WHERE email = ? LIMIT 1");
                $stmt->execute([$formData['email']]);
                if ($stmt->fetch()) {
                    $errors[] = 'An application with this email already exists. Check your status below.';
                }
                if (empty($errors)) {
                    $stmt = $db->prepare("SELECT id FROM employees WHERE email = ? LIMIT 1");
                    $stmt->execute([$formData['email']]);
                    if ($stmt->fetch()) {
                        $errors[] = 'This email is already associated with an active employee account.';
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'A system error occurred during email validation. Please try again.';
            }
        }

        // 9. Insert into registrations table if no errors
        if (empty($errors)) {
            try {
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    INSERT INTO registrations
                        (full_name, honorific, age, gender, email, contact_number,
                         employee_type, department_id, specialization, password_hash,
                         status, applied_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $formData['full_name'],
                    $formData['honorific'],
                    $formData['age'],
                    $formData['gender'],
                    $formData['email'],
                    $formData['contact_number'],
                    $formData['employee_type'],
                    $formData['department_id'],
                    $formData['specialization'],
                    $passwordHash,
                ]);
                $registrationId = $db->lastInsertId();

                // Redirect to status page with registration ID
                flashMessage('success', 'Your application has been submitted! Use the reference below to track your status.');
                header('Location: ' . appUrl('status.php?ref=' . $registrationId));
                exit;
            } catch (Exception $e) {
                $errors[] = 'Failed to submit application. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register';
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
        .register-wrapper { max-width: 620px; margin: 0 auto; }
        .register-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(30,100,180,0.13);
            padding: 2.5rem 2rem;
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

        /* Progress steps */
        .progress-steps {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 1.8rem; gap: 0;
        }
        .step { display: flex; flex-direction: column; align-items: center; }
        .step-num {
            width: 32px; height: 32px; border-radius: 50%;
            background: #e3f0fb; color: #1a73e8; font-weight: 700;
            font-size: 0.88rem; display: flex; align-items: center; justify-content: center;
            border: 2px solid #1a73e8;
        }
        .step.active .step-num { background: #1a73e8; color: #fff; }
        .step.done   .step-num { background: #28a745; color: #fff; border-color: #28a745; }
        .step-label { font-size: 0.7rem; color: #5c6b8a; margin-top: 0.3rem; white-space: nowrap; }
        .step.active .step-label { color: #1a73e8; font-weight: 600; }
        .step-connector {
            flex: 1; height: 2px; background: #c5d4ea;
            min-width: 30px; max-width: 60px; margin-bottom: 1rem;
        }

        .form-row { display: flex; gap: 1rem; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 1.1rem; }
        .form-group label {
            display: block; font-size: 0.85rem; font-weight: 600;
            color: #344055; margin-bottom: 0.35rem;
        }
        .form-group label .req { color: #e74c3c; margin-left: 2px; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%; padding: 0.6rem 0.85rem;
            border: 1.5px solid #c5d4ea; border-radius: 8px;
            font-size: 0.93rem; background: #f7faff;
            box-sizing: border-box; transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus { outline: none; border-color: #1a73e8; background: #fff; }

        .section-divider {
            font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: #1a73e8;
            border-bottom: 2px solid #e3f0fb; padding-bottom: 0.3rem;
            margin: 1.4rem 0 1rem;
        }
        .alert { padding: 0.65rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.87rem; }
        .alert-danger  { background: #fde8e8; color: #c0392b; border: 1px solid #f5c2c2; }
        .alert-danger ul { margin: 0.3rem 0 0 1rem; padding: 0; }
        .btn-register {
            width: 100%; padding: 0.72rem;
            background: linear-gradient(90deg, #1a73e8, #0d47a1);
            color: #fff; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            letter-spacing: 0.03em; transition: opacity 0.2s; margin-top: 0.5rem;
        }
        .btn-register:hover { opacity: 0.88; }
        .back-to-login { text-align: center; margin-top: 1rem; font-size: 0.85rem; color: #5c6b8a; }
        .back-to-login a { color: #1a73e8; text-decoration: none; font-weight: 600; }
        .back-to-login a:hover { text-decoration: underline; }
        #specializationGroup { display: none; }
    </style>
    <link rel="stylesheet" href="<?= htmlspecialchars(appUrl('assets/css/theme.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="register-wrapper">
    <div class="register-card">

        <!-- Header / branding -->
        <div class="card-header">
            <div class="logo-icon">
                <?= uiIcon('calendar-check') ?>
            </div>
            <div class="brand-title">OnTime</div>
            <p class="brand-tagline">A Web-Based Attendance Management System for AICS</p>
            <h1>Employee Registration</h1>
            <p>Complete all fields to submit your application</p>
        </div>

        <!-- Progress steps (visual indicator) -->
        <div class="progress-steps">
            <div class="step done">
                <div class="step-num"><?= uiIcon('check') ?></div>
                <div class="step-label">Start</div>
            </div>
            <div class="step-connector"></div>
            <div class="step active">
                <div class="step-num">2</div>
                <div class="step-label">Fill Form</div>
            </div>
            <div class="step-connector"></div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-label">Review</div>
            </div>
            <div class="step-connector"></div>
            <div class="step">
                <div class="step-num">4</div>
                <div class="step-label">Approved</div>
            </div>
        </div>

        <!-- Flash messages -->
        <?php displayFlashMessage(); ?>

        <!-- Validation errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Registration form -->
        <form method="POST" action="" id="registerForm" novalidate>
            <?= csrfInput() ?>

            <div class="section-divider">Personal Information</div>

            <div class="form-row form-row-name">
                <div class="form-group honorific-field">
                    <label for="honorific">Honorific <span class="req">*</span></label>
                    <select id="honorific" name="honorific" required>
                        <option value="">-- Select --</option>
                        <?php foreach (['Mr.','Ms.','Mrs.','Dr.','Prof.'] as $hon): ?>
                            <option value="<?= htmlspecialchars($hon) ?>"
                                <?= ($formData['honorific'] ?? '') === $hon ? 'selected' : '' ?>>
                                <?= htmlspecialchars($hon) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>"
                           placeholder="Last Name, First Name MI." required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="age">Age <span class="req">*</span></label>
                    <input type="number" id="age" name="age" min="18" max="80"
                           value="<?= htmlspecialchars((string)($formData['age'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender <span class="req">*</span></label>
                    <select id="gender" name="gender" required>
                        <option value="">-- Select --</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option value="<?= $g ?>"
                                <?= ($formData['gender'] ?? '') === $g ? 'selected' : '' ?>>
                                <?= $g ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="section-divider">Contact Information</div>

            <div class="form-group">
                <label for="email">Email Address <span class="req">*</span></label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                       placeholder="example@school.edu.ph" required>
            </div>
            <div class="form-group">
                <label for="contact_number">Contact Number <span class="req">*</span></label>
                <input type="text" id="contact_number" name="contact_number"
                       value="<?= htmlspecialchars($formData['contact_number'] ?? '') ?>"
                       placeholder="09XXXXXXXXX" required>
            </div>

            <div class="section-divider">Employment Details</div>

            <div class="form-group">
                <label for="employee_type">Employee Type <span class="req">*</span></label>
                <select id="employee_type" name="employee_type" required>
                    <option value="">-- Select --</option>
                    <option value="instructor"
                        <?= ($formData['employee_type'] ?? '') === 'instructor' ? 'selected' : '' ?>>
                        Instructor
                    </option>
                    <option value="staff"
                        <?= ($formData['employee_type'] ?? '') === 'staff' ? 'selected' : '' ?>>
                        School Staff
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="department_id">Department <span class="req">*</span></label>
                <select id="department_id" name="department_id" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>"
                            <?= ($formData['department_id'] ?? 0) == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Shown only when Instructor is selected -->
            <div class="form-group" id="specializationGroup">
                <label for="specialization">Field of Specialization <span class="req">*</span></label>
                <input type="text" id="specialization" name="specialization"
                       value="<?= htmlspecialchars($formData['specialization'] ?? '') ?>"
                       placeholder="e.g. Computer Science, Mathematics">
            </div>

            <div class="section-divider">Account Security</div>

            <div class="form-group">
                <label for="password">Password <span class="req">*</span></label>
                <input type="password" id="password" name="password"
                       placeholder="Minimum 8 characters" minlength="8" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password"
                       placeholder="Re-enter password" required>
            </div>

            <button type="submit" class="btn-register"><?= uiIcon('clipboard') ?> Submit Application</button>
        </form>

        <div class="back-to-login">
            Already have an account?
            <a href="<?= htmlspecialchars(appUrl('login.php'), ENT_QUOTES, 'UTF-8') ?>">Sign in here</a>
            &nbsp;|&nbsp;
            <a href="<?= htmlspecialchars(appUrl('status.php'), ENT_QUOTES, 'UTF-8') ?>">Check application status</a>
        </div>
    </div>
</div>

<script>
(function () {
    var empType   = document.getElementById('employee_type');
    var specGroup = document.getElementById('specializationGroup');
    var specInput = document.getElementById('specialization');

    function toggleSpec() {
        if (empType.value === 'instructor') {
            specGroup.style.display = 'block';
            specInput.setAttribute('required', 'required');
        } else {
            specGroup.style.display = 'none';
            specInput.removeAttribute('required');
        }
    }

    empType.addEventListener('change', toggleSpec);
    toggleSpec(); // Run on load to handle POST repopulation
})();
</script>
</body>
</html>
