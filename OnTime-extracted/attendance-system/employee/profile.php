<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (employeePostIsValid()) {
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['contact_number'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || strlen($phone) > 20) {
        employeeRedirect('profile.php', 'danger', 'Enter a valid email address and contact number.');
    }
    try {
        $stmt = $db->prepare("UPDATE employees SET email = ?, contact_number = ? WHERE id = ?");
        $stmt->execute([$email, $phone, $employeeId]);
        logAudit($db, (int)$_SESSION['user_id'], 'Update Profile', 'Employee updated contact information.', 'employees', $employeeId);
        employeeRedirect('profile.php', 'success', 'Profile updated.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            employeeRedirect('profile.php', 'danger', 'That email address is already used by another employee.');
        }
        error_log('Profile update failed: ' . $e->getMessage());
        employeeRedirect('profile.php', 'danger', 'Unable to update your profile.');
    }
}

$stmt = $db->prepare("
    SELECT e.*, d.name AS department_name, p.title AS position_title
    FROM employees e LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN positions p ON p.id = e.position_id WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    exit('Employee profile not found.');
}

employeePageStart('My Profile', 'View your employee information and update contact details.');
?>
<div class="card"><div class="card-body">
    <p><strong>Name:</strong> <?= employeeEscape($profile['honorific'] . ' ' . $profile['full_name']) ?></p>
    <p><strong>Employee code:</strong> <?= employeeEscape($profile['employee_code']) ?></p>
    <p><strong>Type:</strong> <?= employeeEscape(ucfirst($profile['employee_type'])) ?></p>
    <p><strong>Department:</strong> <?= employeeEscape($profile['department_name'] ?? '—') ?></p>
    <p><strong>Position:</strong> <?= employeeEscape($profile['position_title'] ?? '—') ?></p>
    <?php if ($profile['specialization']): ?><p><strong>Specialization:</strong> <?= employeeEscape($profile['specialization']) ?></p><?php endif; ?>
    <form method="post" class="form-grid"><?= csrfInput() ?>
        <label class="form-group"><span class="form-label">Email</span><input class="form-control" type="email" name="email" maxlength="150" value="<?= employeeEscape($profile['email']) ?>" required></label>
        <label class="form-group"><span class="form-label">Contact number</span><input class="form-control" name="contact_number" maxlength="20" value="<?= employeeEscape($profile['contact_number'] ?? '') ?>"></label>
        <div><button class="btn btn-primary">Save contact details</button></div>
    </form>
</div></div>
<?php employeePageEnd(); ?>
