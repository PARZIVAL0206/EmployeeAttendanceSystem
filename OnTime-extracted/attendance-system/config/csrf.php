<?php
// ============================================================
//  CSRF Protection Helpers
//  Include session.php before this file so the session is open.
// ============================================================

/**
 * Generate a CSRF token and store it in the session.
 * Returns the token string.
 * Tokens are regenerated once per session, not per form load.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // bin2hex(random_bytes(32)) produces a 64-char hex string
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the submitted CSRF token against the session token.
 *
 * @param string $token The token received from the form submission.
 * @return bool Whether the submitted token matches the session token.
 */
function validateCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Return an HTML hidden input element containing the CSRF token.
 * Paste <?= csrfInput() ?> inside every <form> that POSTs data.
 */
function csrfInput(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
