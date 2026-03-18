<?php
declare(strict_types=1);

/**
 * Central DB config for local + hosting.
 *
 * Recommended: create `db-config.override.php` (same folder) on your hosting
 * with your real DB credentials, for example:
 *
 *   <?php
 *   define("PLACEMENTHUB_DB_HOST", "sql108.infinityfree.com");
 *   define("PLACEMENTHUB_DB_USER", "if0_41424809");
 *   define("PLACEMENTHUB_DB_PASS", "YOUR_DB_PASSWORD");
 *   define("PLACEMENTHUB_DB_NAME", "if0_41424809_placementhub");
 *
 * This lets you keep local defaults in this file without editing it.
 *
 * Precedence: environment variables > override constants > defaults.
 *
 * Optionally, you can use environment variables instead:
 * - PLACEMENTHUB_DB_HOST / _USER / _PASS / _NAME
 */

$override = __DIR__ . "/db-config.override.php";
if (is_file($override)) {
    require_once $override;
}

if (!defined("PLACEMENTHUB_DB_HOST")) {
    define("PLACEMENTHUB_DB_HOST", "localhost");
}
if (!defined("PLACEMENTHUB_DB_USER")) {
    define("PLACEMENTHUB_DB_USER", "root");
}
if (!defined("PLACEMENTHUB_DB_PASS")) {
    define("PLACEMENTHUB_DB_PASS", "");
}
if (!defined("PLACEMENTHUB_DB_NAME")) {
    define("PLACEMENTHUB_DB_NAME", "detailsdb");
}

function placementhub_db_config(): array
{
    $host = getenv("PLACEMENTHUB_DB_HOST");
    $user = getenv("PLACEMENTHUB_DB_USER");
    $pass = getenv("PLACEMENTHUB_DB_PASS");
    $name = getenv("PLACEMENTHUB_DB_NAME");

    return [
        "host" => ($host !== false && trim((string)$host) !== "") ? trim((string)$host) : PLACEMENTHUB_DB_HOST,
        "user" => ($user !== false && trim((string)$user) !== "") ? trim((string)$user) : PLACEMENTHUB_DB_USER,
        "pass" => ($pass !== false) ? (string)$pass : PLACEMENTHUB_DB_PASS,
        "name" => ($name !== false && trim((string)$name) !== "") ? trim((string)$name) : PLACEMENTHUB_DB_NAME,
    ];
}
