<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';
requireAdmin();

$db = getDB();

function adminEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function adminRedirect(string $path, string $type, string $message): void
{
    flashMessage($type, $message);
    header('Location: ' . appUrl('admin/' . ltrim($path, '/')));
    exit;
}

function adminPostIsValid(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        adminRedirect(basename($_SERVER['PHP_SELF']), 'danger', 'Your session expired. Please retry the action.');
    }
    return true;
}

function adminAudit(PDO $db, string $action, string $description, string $table, ?int $id): void
{
    logAudit($db, (int)$_SESSION['user_id'], $action, $description, $table, $id);
}
