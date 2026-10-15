<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

// Public AJAX endpoint — only reveals role type, not sensitive data
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($username === '') {
    echo json_encode(['found' => false]);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT role, status FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['found' => false]);
        exit;
    }

    // Map internal role to display label
    $roleLabels = [
        'admin'    => 'Administrator',
        'employee' => 'Instructor / School Staff',
    ];
    $roleDisplay = $roleLabels[$user['role']] ?? ucfirst($user['role']);

    echo json_encode([
        'found'  => true,
        'role'   => $roleDisplay,
        'status' => $user['status'],
    ]);
} catch (Exception $e) {
    echo json_encode(['found' => false]);
}
