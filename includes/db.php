<?php
/**
 * Database connection.
 *
 * The textbook (Ch. 11, Fig. 11.6) connects with the PEAR DB library:
 *     $d = DB::connect('mysqli://user:pass@host/db');
 *     if (DB::isError($d)) { die(...); }
 *     $d->setErrorHandling(PEAR_ERROR_DIE);
 *
 * PEAR DB is no longer maintained and doesn't run on PHP 8, so this portal
 * uses PDO, PHP's built-in replacement. The ideas map one-to-one:
 *     DB::connect(...)            -> new PDO($dsn, $user, $pass)
 *     DB::isError / die           -> try/catch PDOException
 *     setErrorHandling(DIE)       -> PDO::ERRMODE_EXCEPTION
 *     query($sql, array(...))     -> prepare($sql)->execute([...])   (? placeholders)
 *     $q->fetchRow() / getAll()   -> $stmt->fetch() / fetchAll()
 */

function db(): PDO
{
    static $pdo = null;              // one connection per request
    if ($pdo !== null) {
        return $pdo;
    }

    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        http_response_code(500);
        exit('Missing config/config.php. Copy config/config.example.php to config/config.php and edit it. See README.md.');
    }
    $config = require $configFile;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // like PEAR_ERROR_DIE, but catchable
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real server-side prepared statements
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        $detail = !empty($config['debug']) ? ' (' . htmlspecialchars($e->getMessage()) . ')' : '';
        exit('Cannot connect to the database. Check that MySQL is running in XAMPP and that config/config.php is correct.' . $detail);
    }

    return $pdo;
}

function app_config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/../config/config.php';
        $config = is_file($file) ? require $file : [];
    }
    return $config[$key] ?? $default;
}
