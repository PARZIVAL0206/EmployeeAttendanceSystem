<?php
require_once __DIR__ . '/../includes/icons.php';

function adminPageStart(string $title, string $subtitle = ''): void
{
    $pageTitle = $title;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= adminEscape($title) ?> - OnTime: A Web-Based Attendance Management System for AICS</title>
        <link rel="stylesheet" href="<?= adminEscape(appUrl('assets/css/style.css')) ?>">
        <link rel="stylesheet" href="<?= adminEscape(appUrl('assets/css/theme.css')) ?>">
    </head>
    <body>
    <?php
    require __DIR__ . '/../includes/sidebar_admin.php';
    ?>
    <div class="main-content">
        <header class="top-header">
            <div class="header-left"><button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false"><?= uiIcon('menu') ?></button><strong><?= adminEscape($title) ?></strong></div>
            <div class="header-right"><span class="header-user-name"><?= adminEscape($_SESSION['full_name'] ?? 'Administrator') ?></span></div>
        </header>
        <main class="page-content">
            <h1 class="page-title"><?= adminEscape($title) ?></h1>
            <?php if ($subtitle !== ''): ?><p class="page-subtitle"><?= adminEscape($subtitle) ?></p><?php endif; ?>
            <?php displayFlashMessage(); ?>
    <?php
}

function adminPageEnd(): void
{
    ?>
        </main>
    </div>
    <?php require __DIR__ . '/../includes/footer.php'; ?>
    <?php
}

function adminTableStart(array $headers): void
{
    echo '<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . adminEscape($header) . '</th>';
    }
    echo '</tr></thead><tbody>';
}

function adminTableEnd(): void
{
    echo '</tbody></table></div></div>';
}
