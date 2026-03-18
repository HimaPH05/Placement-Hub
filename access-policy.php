<?php
/**
 * Student-only access policy.
 *
 * Mode options:
 * - domain: only emails from allowed domains can signup/login
 * - off: no restriction
 */

declare(strict_types=1);

const STUDENT_ACCESS_MODE = "domain";

/**
 * Only used when STUDENT_ACCESS_MODE === "domain".
 * Put domains WITHOUT the "@" (example: "geckkd.ac.in").
 */
function get_allowed_student_email_domains(): array
{
    return [
        "geckkd.ac.in",
    ];
}

function student_access_normalize_email_domain(string $email): string
{
    $email = trim($email);
    $atPos = strrpos($email, "@");
    if ($atPos === false) {
        return "";
    }

    return strtolower(trim(substr($email, $atPos + 1)));
}

function student_access_is_email_domain_allowed(string $email): bool
{
    $allowed = get_allowed_student_email_domains();
    if (count($allowed) === 0) {
        return false;
    }

    $domain = student_access_normalize_email_domain($email);
    if ($domain === "") {
        return false;
    }

    foreach ($allowed as $d) {
        if ($domain === strtolower(trim((string)$d))) {
            return true;
        }
    }
    return false;
}

/**
 * Returns [allowed(bool), message(string)].
 */
function enforce_student_access_policy(mysqli $conn, string $regno, string $email): array
{
    $mode = strtolower(trim((string)STUDENT_ACCESS_MODE));
    if ($mode === "off") {
        return [true, ""];
    }

    if ($mode === "domain") {
        if (!student_access_is_email_domain_allowed($email)) {
            return [false, "Only college students can access. Please use your college email address."];
        }
        return [true, ""];
    }

    // Any other mode is treated as off to avoid accidental lockouts.
    return [true, ""];
}
