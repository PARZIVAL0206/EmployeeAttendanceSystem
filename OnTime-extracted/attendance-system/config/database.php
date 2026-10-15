<?php
// ============================================================
//  Database Configuration
//  XAMPP default database connection.
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'attendance_system');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * The connection is created once and reused for the rest of the request.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST
                 . ';dbname='   . DB_NAME
                 . ';charset='  . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on error
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arrays by default
                PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared stmts
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            // Never expose credentials — show a friendly message
            die(
                '<div style="font-family:sans-serif;padding:30px;color:#E53935;">'
              . '<h2><svg viewBox="0 0 24 24" aria-hidden="true" style="width:1em;height:1em;vertical-align:-.15em;margin-right:.4em" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2 21h20L12 3z"/><path d="M12 9v5m0 3h.01"/></svg> Database Connection Failed</h2>'
              . '<p>Please make sure XAMPP MySQL is running and the database has been imported.</p>'
              . '<p><small>Error: ' . htmlspecialchars($e->getMessage()) . '</small></p>'
              . '</div>'
            );
        }
    }

    return $pdo;
}
