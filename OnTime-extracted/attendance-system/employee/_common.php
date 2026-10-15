<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
requireEmployee();

$db = getDB();
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($employeeId <= 0) {
    http_response_code(403);
    exit('This account is not linked to an employee profile.');
}

function employeeEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function employeeRedirect(string $path, string $type, string $message): void
{
    flashMessage($type, $message);
    header('Location: ' . appUrl('employee/' . ltrim($path, '/')));
    exit;
}

function employeePostIsValid(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        employeeRedirect(basename($_SERVER['PHP_SELF']), 'danger', 'Your session expired. Please retry the action.');
    }
    return true;
}
