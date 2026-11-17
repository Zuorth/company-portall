<?php
/**
 * Copy this file to config.php and fill in your own values:
 *   copy config\config.example.php config\config.php      (Windows)
 *   cp config/config.example.php config/config.php         (Mac/Linux)
 *
 * config.php is listed in .gitignore, so your real password
 * never ends up on GitHub.
 */
return [
    'db_host'     => 'localhost',
    'db_port'     => 3306,
    'db_name'     => 'company_portal',
    'db_user'     => 'portal_app',            // least-privilege account from setup.sql
    'db_password' => 'ChangeMe_Portal2026!',  // must match setup.sql
    'app_name'    => 'Company Staff Portal',
    'debug'       => false,                   // true shows raw DB errors (never in production)
];
