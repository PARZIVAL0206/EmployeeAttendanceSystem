<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_layout.php';

if (employeePostIsValid()) {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (strlen($new) < 8 || $new !== $confirm) {
        employeeRedirect('change_password.php', 'danger', 'The new password must be at least 8 characters and match the confirmation.');
    }
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ? AND employee_id = ?");
    $stmt->execute([(int)$_SESSION['user_id'], $employeeId]);
    $hash = $stmt->fetchColumn();
    if (!is_string($hash) || !password_verify($current, $hash)) {
        employeeRedirect('change_password.php', 'danger', 'Current password is incorrect.');
    }
    try {
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
        logAudit($db, (int)$_SESSION['user_id'], 'Change Password', 'Employee changed account password.', 'users', (int)$_SESSION['user_id']);
        employeeRedirect('change_password.php', 'success', 'Password changed.');
    } catch (Throwable $e) {
        error_log('Password update failed: ' . $e->getMessage());
        employeeRedirect('change_password.php', 'danger', 'Unable to change your password.');
    }
}

employeePageStart('Change Password', 'Choose a new password with at least 8 characters.');
?>
<div class="card"><div class="card-body"><form method="post" class="form-grid"><?= csrfInput() ?>
    <label class="form-group form-grid-full"><span class="form-label">Current password</span><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></label>
    <label class="form-group"><span class="form-label">New password</span><input class="form-control" type="password" name="new_password" minlength="8" autocomplete="new-password" required></label>
    <label class="form-group"><span class="form-label">Confirm new password</span><input class="form-control" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></label>
    <div><button class="btn btn-primary">Change password</button></div>
</form></div></div>
<?php employeePageEnd(); ?>
