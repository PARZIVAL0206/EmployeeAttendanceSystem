<?php
// ============================================================
//  Session & Timezone Configuration
//  Include this file at the top of every page.
// ============================================================

require_once __DIR__ . '/helpers.php';

// Start session only if one hasn't already been started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// All dates/times will use Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// ── Guard Functions ──────────────────────────────────────────

/**
 * Redirect to login page if the user is not authenticated.
 * Call this on every protected page.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . appUrl('login.php'));
        exit;
    }
}

/**
 * Redirect non-admins to the employee dashboard.
 * Call this on every admin-only page.
 */
function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . appUrl('employee/dashboard.php'));
        exit;
    }
}

/**
 * Redirect admins to their own dashboard.
 * Call this on employee-only pages.
 */
function requireEmployee(): void
{
    requireLogin();
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . appUrl('admin/dashboard.php'));
        exit;
    }
}

/**
 * If the user is already logged in, redirect them to the correct dashboard.
 * Use on login / register pages so authenticated users skip them.
 */
function redirectIfLoggedIn(): void
{
    if (isset($_SESSION['user_id'])) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: ' . appUrl('admin/dashboard.php'));
        } else {
            header('Location: ' . appUrl('employee/dashboard.php'));
        }
        exit;
    }
}
