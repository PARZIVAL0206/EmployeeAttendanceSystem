<?php
require_once __DIR__ . '/../includes/icons.php';

function employeePageStart(string $title, string $subtitle = ''): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= employeeEscape($title) ?> - OnTime: A Web-Based Attendance Management System for AICS</title>
        <link rel="stylesheet" href="<?= employeeEscape(appUrl('assets/css/style.css')) ?>">
        <link rel="stylesheet" href="<?= employeeEscape(appUrl('assets/css/theme.css')) ?>">
    </head>
    <body>
    <?php
    require __DIR__ . '/../includes/sidebar_employee.php';
    ?>
    <div class="main-content">
        <header class="top-header">
            <div class="header-left"><button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false"><?= uiIcon('menu') ?></button><strong><?= employeeEscape($title) ?></strong></div>
            <div class="header-right"><span class="header-user-name"><?= employeeEscape($_SESSION['full_name'] ?? 'Employee') ?></span></div>
        </header>
        <main class="page-content">
            <h1 class="page-title"><?= employeeEscape($title) ?></h1>
            <?php if ($subtitle !== ''): ?><p class="page-subtitle"><?= employeeEscape($subtitle) ?></p><?php endif; ?>
            <?php displayFlashMessage(); ?>
    <?php
}

function employeePageEnd(): void
{
    ?>
        </main>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
    <?php
}
