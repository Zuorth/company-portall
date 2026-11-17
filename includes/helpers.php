<?php
/**
 * Small helpers shared by every page: sessions, CSRF protection,
 * output escaping, flash messages and the audit log.
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,     // JavaScript can't read the session cookie
        'cookie_samesite' => 'Lax',
    ]);
}

/** Escape anything printed into HTML (stops XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** CSRF token: every form carries it, every POST must return it. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string) $_POST['csrf']);
}

/** Flash message: survives exactly one redirect (Post/Redirect/Get). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Trimmed POST value, or '' if missing. */
function post(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

/** Record every form submission in SUBMISSION_LOG. Never breaks the page. */
function audit(string $form, string $outcome, string $summary): void
{
    try {
        db()->prepare(
            'INSERT INTO SUBMISSION_LOG (Form_name, Outcome, Summary, Client_ip) VALUES (?, ?, ?, ?)'
        )->execute([$form, $outcome, mb_substr($summary, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (PDOException $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/** Format a 9-digit SSN as 123-45-6789 for display. */
function fmt_ssn(?string $ssn): string
{
    return ($ssn && strlen($ssn) === 9)
        ? substr($ssn, 0, 3) . '-' . substr($ssn, 3, 2) . '-' . substr($ssn, 5)
        : (string) $ssn;
}

function money($n): string
{
    return $n === null ? '-' : '$' . number_format((float) $n, 0);
}

/** Dropdown data used by several forms. */
function departments(): array
{
    return db()->query('SELECT Dnumber, Dname FROM DEPARTMENT ORDER BY Dname')->fetchAll();
}

function employees_for_select(): array
{
    return db()->query('SELECT Ssn, Fname, Lname FROM EMPLOYEE ORDER BY Lname, Fname')->fetchAll();
}

function projects(): array
{
    return db()->query('SELECT Pnumber, Pname, Plocation FROM PROJECT ORDER BY Pname')->fetchAll();
}
