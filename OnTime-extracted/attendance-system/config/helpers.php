<?php
// ============================================================
//  helpers.php — Global Utility Functions
//  Include at the top of every page (after database.php).
// ============================================================

/**
 * Build a URL rooted at this application, regardless of its web-server path.
 */
function appUrl(string $path = ''): string
{
    static $basePath = null;

    if ($basePath === null) {
        $scriptFilename = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
        $applicationRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');

        if ($scriptFilename === false || $applicationRoot === false) {
            throw new RuntimeException('Unable to determine the application URL path.');
        }

        $scriptDirectory = str_replace('\\', '/', dirname($scriptFilename));
        $applicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');

        if (strcasecmp($scriptDirectory, $applicationRoot) === 0) {
            $relativeDirectories = '';
        } elseif (stripos($scriptDirectory, $applicationRoot . '/') === 0) {
            $relativeDirectories = substr($scriptDirectory, strlen($applicationRoot) + 1);
        } else {
            throw new RuntimeException('The requested script is outside the application directory.');
        }

        $scriptUrlDirectory = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $urlSegments = ($scriptUrlDirectory === '' || $scriptUrlDirectory === '.')
            ? []
            : explode('/', $scriptUrlDirectory);
        $directoryDepth = ($relativeDirectories === '')
            ? 0
            : substr_count($relativeDirectories, '/') + 1;

        if ($directoryDepth > count($urlSegments)) {
            throw new RuntimeException('Unable to map the application directory to its URL path.');
        }

        if ($directoryDepth > 0) {
            array_splice($urlSegments, -$directoryDepth);
        }

        $basePath = $urlSegments ? '/' . implode('/', $urlSegments) : '';
    }

    return $basePath . '/' . ltrim($path, '/');
}

// ── Input / Output Sanitization ──────────────────────────────

/**
 * Sanitize a value for safe output in HTML.
 * Trims whitespace and converts special chars to HTML entities.
 *
 * @param  mixed  $data
 * @return string
 */
function sanitize($data): string
{
    return htmlspecialchars(trim((string) $data), ENT_QUOTES, 'UTF-8');
}

// ── Date / Time Formatting ────────────────────────────────────

/**
 * Convert a 24-hour time string (HH:MM or HH:MM:SS) to 12-hour format.
 * Example: "07:00:00" -> "7:00 AM"
 *
 * @param  string|null $time
 * @return string
 */
function formatTime(?string $time): string
{
    if ($time === null || $time === '') {
        return '--';
    }
    return date('g:i A', strtotime($time));
}

/**
 * Format a DATETIME or timestamp string to a human-readable form.
 * Example: "2024-01-15 07:03:22" -> "Jan 15, 2024 7:03 AM"
 *
 * @param  string|null $datetime
 * @return string
 */
function formatDateTime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '--';
    }
    return date('M j, Y g:i A', strtotime($datetime));
}

/**
 * Format a DATE string to a human-readable form.
 * Example: "2024-01-15" -> "January 15, 2024"
 *
 * @param  string|null $date
 * @return string
 */
function formatDate(?string $date): string
{
    if ($date === null || $date === '') {
        return '--';
    }
    return date('F j, Y', strtotime($date));
}

// ── Attendance Calculations ───────────────────────────────────

/**
 * Calculate the difference in decimal hours between two DATETIME strings.
 * Returns 0.00 if either value is missing.
 *
 * @param  string|null $timeIn   DATETIME string
 * @param  string|null $timeOut  DATETIME string
 * @return float
 */
function calculateHours(?string $timeIn, ?string $timeOut): float
{
    if ($timeIn === null || $timeOut === null || $timeIn === '' || $timeOut === '') {
        return 0.00;
    }

    $in  = new DateTime($timeIn);
    $out = new DateTime($timeOut);

    // Handle overnight shift: if time-out is before time-in, add 1 day
    if ($out < $in) {
        $out->modify('+1 day');
    }

    $seconds = $out->getTimestamp() - $in->getTimestamp();
    return round($seconds / 3600, 2);
}

/**
 * Determine attendance status (present / late) based on scheduled time-in.
 *
 * Logic:
 *   - Actual time-in within grace period  -> 'present'
 *   - Actual time-in beyond grace period  -> 'late'
 *
 * @param  string $scheduledTimeIn      TIME string "HH:MM:SS"
 * @param  string $actualTimeIn         DATETIME string
 * @param  int    $gracePeriodMinutes   Grace window in minutes
 * @return string  'present' | 'late'
 */
function determineAttendanceStatus(string $scheduledTimeIn, string $actualTimeIn, int $gracePeriodMinutes): string
{
    // Use the actual check-in date to build a full datetime for the scheduled time
    $date      = date('Y-m-d', strtotime($actualTimeIn));
    $scheduled = new DateTime($date . ' ' . $scheduledTimeIn);
    $actual    = new DateTime($actualTimeIn);

    // Deadline = scheduled time + grace period minutes
    $deadline = clone $scheduled;
    $deadline->modify("+{$gracePeriodMinutes} minutes");

    return ($actual <= $deadline) ? 'present' : 'late';
}

/**
 * Determine the completion status of an attendance record.
 *
 * Logic:
 *   - No actual time-out yet                              -> 'pending'
 *   - Total hours >= required hours                       -> 'completed'
 *   - Actual time-out is before scheduled time-out        -> 'early_leave'
 *   - Hours short but clocked out at/after scheduled time -> 'incomplete'
 *
 * @param  string      $scheduledTimeOut   TIME "HH:MM:SS"
 * @param  string|null $actualTimeOut      DATETIME or null
 * @param  float       $requiredHours      e.g. 8.00
 * @param  float       $totalHours         Calculated hours worked
 * @return string  'completed' | 'early_leave' | 'incomplete' | 'pending'
 */
function determineCompletionStatus(
    string  $scheduledTimeOut,
    ?string $actualTimeOut,
    float   $requiredHours,
    float   $totalHours
): string {
    // Not clocked out yet
    if ($actualTimeOut === null || $actualTimeOut === '') {
        return 'pending';
    }

    // Worked enough hours
    if ($totalHours >= $requiredHours) {
        return 'completed';
    }

    // Compare actual time-out against scheduled time-out
    $date      = date('Y-m-d', strtotime($actualTimeOut));
    $scheduled = new DateTime($date . ' ' . $scheduledTimeOut);
    $actual    = new DateTime($actualTimeOut);

    if ($actual < $scheduled) {
        return 'early_leave';
    }

    // Clocked out on time or after, but hours still short
    return 'incomplete';
}

// ── Employee Code Generation ──────────────────────────────────

/**
 * Generate the next instructor employee code.
 * Format: prof001, prof002, prof003, ...
 *
 * @param  PDO $pdo
 * @return string
 */
function generateProfessorCode(PDO $pdo): string
{
    $stmt = $pdo->prepare(
        "SELECT employee_code FROM employees
         WHERE employee_type = 'instructor'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute();
    $last = $stmt->fetchColumn();

    if ($last === false) {
        return 'prof001';
    }

    // Strip 'prof' prefix, parse integer, increment
    $num = (int) ltrim(str_ireplace('prof', '', $last), '0');
    return 'prof' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate the next staff employee code.
 * Format: 1000, 1001, 1002, ...
 *
 * @param  PDO $pdo
 * @return string
 */
function generateStaffCode(PDO $pdo): string
{
    $stmt = $pdo->prepare(
        "SELECT employee_code FROM employees
         WHERE employee_type = 'staff'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute();
    $last = $stmt->fetchColumn();

    if ($last === false) {
        return '1000';
    }

    return (string) ((int) $last + 1);
}

// ── Username Generation ───────────────────────────────────────

/**
 * Generate a username from the employee's full name and employee code.
 *
 * The surname is followed immediately by the employee code, e.g.
 * "lumasagprof001" or "lumasag1000". Names entered as "Surname, Given"
 * use the text before the comma; other names use the final word.
 *
 * @param  string $fullName      Employee's full name
 * @param  string $employeeCode  e.g. "prof001" or "1000"
 * @param  string $employeeType  'instructor' | 'staff'
 * @return string
 */
function generateUsername(string $fullName, string $employeeCode, string $employeeType): string
{
    $nameParts = explode(',', trim($fullName), 2);
    if (count($nameParts) > 1) {
        $lastName = trim($nameParts[0]);
    } else {
        $parts = preg_split('/\s+/', trim($fullName));
        $lastName = (string)end($parts);
    }

    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lastName);
        if ($transliterated !== false) {
            $lastName = $transliterated;
        }
    }
    $lastName = strtolower(preg_replace('/[^a-z0-9]/i', '', $lastName));

    if ($lastName === '') {
        $lastName = 'user';
    }

    return $lastName . $employeeCode;
}

// ── Audit Logging ─────────────────────────────────────────────

/**
 * Insert a row into audit_logs.
 * Call this after every significant create / update / delete action.
 *
 * @param  PDO         $pdo
 * @param  int         $userId        The user performing the action
 * @param  string      $action        Short description, e.g. "Approved Registration"
 * @param  string      $description   Full description of what happened
 * @param  string|null $table         The database table affected (or null)
 * @param  int|null    $affectedId    Primary key of the affected row (or null)
 */
function logAudit(
    PDO     $pdo,
    int     $userId,
    string  $action,
    string  $description,
    ?string $table      = null,
    ?int    $affectedId = null
): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs
           (user_id, action, description, affected_table, affected_id, ip_address)
         VALUES
           (:user_id, :action, :description, :table, :affected_id, :ip)"
    );
    $stmt->execute([
        ':user_id'     => $userId,
        ':action'      => $action,
        ':description' => $description,
        ':table'       => $table,
        ':affected_id' => $affectedId,
        ':ip'          => $ip,
    ]);
}

// ── Notifications ─────────────────────────────────────────────

/**
 * Insert a notification for a specific user.
 *
 * @param  PDO    $pdo
 * @param  int    $userId
 * @param  string $title
 * @param  string $message
 */
function addNotification(PDO $pdo, int $userId, string $title, string $message): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message)
         VALUES (:user_id, :title, :message)"
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':title'   => $title,
        ':message' => $message,
    ]);
}

/**
 * Return the count of unread notifications for a user.
 *
 * @param  PDO $pdo
 * @param  int $userId
 * @return int
 */
function getUnreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE user_id = :user_id AND is_read = 0"
    );
    $stmt->execute([':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

// ── Schedule Helpers ──────────────────────────────────────────

/**
 * Get the currently active schedule for an employee.
 *
 * Returns the schedule row whose effective_date is the most
 * recent date on or before today.
 *
 * @param  PDO $pdo
 * @param  int $employeeId
 * @return array|null  Schedule row, or null if none is assigned
 */
function getCurrentSchedule(PDO $pdo, int $employeeId): ?array
{
    $today = date('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT s.*, es.effective_date
         FROM employee_schedules es
         JOIN schedules s ON s.id = es.schedule_id
         WHERE es.employee_id = :employee_id
           AND es.effective_date <= :today
         ORDER BY es.effective_date DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':today'       => $today,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Check whether a given date falls on a working day for a schedule.
 *
 * The schedule's work_days column is a JSON array such as:
 *   ["Mon","Tue","Wed","Thu","Fri"]
 *
 * PHP date('D') returns the same 3-letter abbreviations.
 *
 * @param  array  $schedule  Row from the schedules table
 * @param  string $date      Date string in 'Y-m-d' format
 * @return bool
 */
function isWorkDay(array $schedule, string $date): bool
{
    $workDays = json_decode($schedule['work_days'], true);

    if (!is_array($workDays) || empty($workDays)) {
        return false;
    }

    // e.g. "Mon", "Tue" ...
    $dayAbbr = date('D', strtotime($date));

    return in_array($dayAbbr, $workDays, true);
}

// ── Flash Messages ────────────────────────────────────────────

/**
 * Store a one-time flash message in the session.
 *
 * @param  string $type     'success' | 'danger' | 'warning' | 'info'
 * @param  string $message  Message text
 */
function flashMessage(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Output the flash message as an HTML alert div, then remove it.
 * Call this once near the top of the page content area.
 */
function displayFlashMessage(): void
{
    if (!isset($_SESSION['flash'])) {
        return;
    }

    $type    = htmlspecialchars($_SESSION['flash']['type'],    ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8');

    echo '<div class="alert alert-' . $type . '" role="alert">'
       . '<span>' . $message . '</span>'
       . '<button type="button" class="alert-close" aria-label="Dismiss alert" onclick="this.parentElement.remove()">'
       . '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
       . 'stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m18 6-12 12M6 6l12 12"/></svg>'
       . '</button>'
       . '</div>' . PHP_EOL;

    unset($_SESSION['flash']);
}
