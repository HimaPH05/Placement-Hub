<?php
declare(strict_types=1);

/**
 * Minimal email sender.
 *
 * SMTP-first sender for hosting reliability.
 *
 * Configure SMTP via environment variables (recommended for hosting):
 * - PLACEMENTHUB_SMTP_HOST (example: smtp-relay.brevo.com)
 * - PLACEMENTHUB_SMTP_PORT (example: 587)
 * - PLACEMENTHUB_SMTP_USER (example: your SMTP username)
 * - PLACEMENTHUB_SMTP_PASS (example: your SMTP password / API key)
 * - PLACEMENTHUB_SMTP_SECURE (one of: tls, ssl, none) default: tls
 * - PLACEMENTHUB_FROM_EMAIL (default: no-reply@geckkd.ac.in)
 * - PLACEMENTHUB_FROM_NAME  (default: Placement Hub)
 *
 * If SMTP isn't configured (or fails), falls back to `mail()`, then logs to `uploads/email_outbox.log`.
 */

const EMAIL_FALLBACK_LOG_PATH = __DIR__ . "/uploads/email_outbox.log";

// Hosting-friendly config: allow defining constants in a local override file.
$emailOverride = __DIR__ . "/email-config.override.php";
if (is_file($emailOverride)) {
    require_once $emailOverride;
}

function email_cfg(string $key, string $default = ""): string
{
    $val = getenv($key);
    if ($val !== false) {
        return trim((string)$val);
    }

    if (defined($key)) {
        $c = constant($key);
        if ($c === null) {
            return $default;
        }
        return trim((string)$c);
    }

    return $default;
}

function email_from_address(): string
{
    $from = email_cfg("PLACEMENTHUB_FROM_EMAIL", "no-reply@geckkd.ac.in");
    return $from !== "" ? $from : "no-reply@geckkd.ac.in";
}

function email_from_name(): string
{
    $name = email_cfg("PLACEMENTHUB_FROM_NAME", "Placement Hub");
    return $name !== "" ? $name : "Placement Hub";
}

function email_smtp_enabled(): bool
{
    return email_cfg("PLACEMENTHUB_SMTP_HOST") !== ""
        && email_cfg("PLACEMENTHUB_SMTP_PORT") !== ""
        && email_cfg("PLACEMENTHUB_SMTP_USER") !== ""
        && email_cfg("PLACEMENTHUB_SMTP_PASS") !== "";
}

function smtp_read_response($fp): array
{
    $lines = [];
    while (!feof($fp)) {
        $line = fgets($fp, 515);
        if ($line === false) {
            break;
        }
        $lines[] = rtrim($line, "\r\n");
        // Multiline responses have a dash after the code, final line has a space.
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }

    $last = $lines[count($lines) - 1] ?? "";
    $code = 0;
    if (preg_match('/^(\d{3})\b/', $last, $m)) {
        $code = (int)$m[1];
    }
    return [$code, implode("\n", $lines)];
}

function smtp_cmd($fp, string $cmd): array
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_read_response($fp);
}

function smtp_expect($fp, string $cmd, array $okCodes): array
{
    [$code, $msg] = smtp_cmd($fp, $cmd);
    if (!in_array($code, $okCodes, true)) {
        return [false, "SMTP error for '{$cmd}': {$code} {$msg}"];
    }
    return [true, ""];
}

function smtp_send_email(string $to, string $subject, string $body): array
{
    $host = email_cfg("PLACEMENTHUB_SMTP_HOST");
    $port = (int)email_cfg("PLACEMENTHUB_SMTP_PORT");
    $user = email_cfg("PLACEMENTHUB_SMTP_USER");
    $pass = email_cfg("PLACEMENTHUB_SMTP_PASS");
    $secure = strtolower(email_cfg("PLACEMENTHUB_SMTP_SECURE", "tls"));
    if ($secure !== "tls" && $secure !== "ssl" && $secure !== "none") {
        $secure = "tls";
    }

    $remote = $host . ":" . $port;
    if ($secure === "ssl") {
        $remote = "ssl://" . $remote;
    }

    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        return [false, "SMTP connect failed: {$errstr} ({$errno})"];
    }
    stream_set_timeout($fp, 20);

    [$code, $greet] = smtp_read_response($fp);
    if ($code !== 220) {
        fclose($fp);
        return [false, "SMTP greeting failed: {$code} {$greet}"];
    }

    $ehloName = "placementhub";
    [$ok, $err] = smtp_expect($fp, "EHLO " . $ehloName, [250]);
    if (!$ok) {
        // Try HELO as a fallback.
        [$ok2, $err2] = smtp_expect($fp, "HELO " . $ehloName, [250]);
        if (!$ok2) {
            fclose($fp);
            return [false, $err . " / " . $err2];
        }
    }

    if ($secure === "tls") {
        [$okTls, $errTls] = smtp_expect($fp, "STARTTLS", [220]);
        if (!$okTls) {
            fclose($fp);
            return [false, $errTls];
        }

        $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($cryptoOk !== true) {
            fclose($fp);
            return [false, "Failed to enable TLS for SMTP connection"];
        }

        // Re-EHLO after STARTTLS.
        [$ok3, $err3] = smtp_expect($fp, "EHLO " . $ehloName, [250]);
        if (!$ok3) {
            fclose($fp);
            return [false, $err3];
        }
    }

    // AUTH LOGIN
    [$okAuth, $errAuth] = smtp_expect($fp, "AUTH LOGIN", [334]);
    if (!$okAuth) {
        fclose($fp);
        return [false, $errAuth];
    }
    [$okUser, $errUser] = smtp_expect($fp, base64_encode($user), [334]);
    if (!$okUser) {
        fclose($fp);
        return [false, $errUser];
    }
    [$okPass, $errPass] = smtp_expect($fp, base64_encode($pass), [235]);
    if (!$okPass) {
        fclose($fp);
        return [false, $errPass];
    }

    $fromEmail = email_from_address();
    $fromName = email_from_name();

    $toClean = str_replace(["\r", "\n"], "", trim($to));
    $fromClean = str_replace(["\r", "\n"], "", trim($fromEmail));

    [$okMail, $errMail] = smtp_expect($fp, "MAIL FROM:<{$fromClean}>", [250]);
    if (!$okMail) {
        fclose($fp);
        return [false, $errMail];
    }
    [$okRcpt, $errRcpt] = smtp_expect($fp, "RCPT TO:<{$toClean}>", [250, 251]);
    if (!$okRcpt) {
        fclose($fp);
        return [false, $errRcpt];
    }

    [$okData, $errData] = smtp_expect($fp, "DATA", [354]);
    if (!$okData) {
        fclose($fp);
        return [false, $errData];
    }

    $subjectClean = str_replace(["\r", "\n"], "", trim($subject));
    $fromHeader = $fromName . " <" . $fromEmail . ">";

    $headers = [];
    $headers[] = "From: " . $fromHeader;
    $headers[] = "To: " . $toClean;
    $headers[] = "Subject: " . $subjectClean;
    $headers[] = "Date: " . gmdate("D, d M Y H:i:s") . " +0000";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $data = str_replace("\r\n", "\n", $data);
    $data = str_replace("\r", "\n", $data);
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        // Dot-stuffing per SMTP rules.
        if (isset($line[0]) && $line[0] === ".") {
            $line = "." . $line;
        }
        fwrite($fp, $line . "\r\n");
    }
    fwrite($fp, ".\r\n");

    [$code2, $msg2] = smtp_read_response($fp);
    if ($code2 !== 250) {
        smtp_cmd($fp, "QUIT");
        fclose($fp);
        return [false, "SMTP DATA not accepted: {$code2} {$msg2}"];
    }

    smtp_cmd($fp, "QUIT");
    fclose($fp);
    return [true, ""];
}

function send_email(string $to, string $subject, string $body): array
{
    $to = trim($to);
    $subject = trim($subject);

    // Prevent header injection.
    $to = str_replace(["\r", "\n"], "", $to);
    $subject = str_replace(["\r", "\n"], "", $subject);

    if (email_smtp_enabled()) {
        [$okSmtp, $errSmtp] = smtp_send_email($to, $subject, $body);
        if ($okSmtp) {
            return [true, ""];
        }
        // Continue to fallbacks below, but preserve SMTP error in the note.
        $smtpNote = "SMTP failed: " . $errSmtp;
    } else {
        $smtpNote = "SMTP not configured.";
    }

    $from = email_from_name() . " <" . email_from_address() . ">";
    $headers = [];
    $headers[] = "From: " . $from;
    $headers[] = "Reply-To: " . email_from_address();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";

    $ok = false;
    if (function_exists("mail")) {
        $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    if ($ok) {
        return [true, ""];
    }

    $entry = "----\n";
    $entry .= "TO: {$to}\n";
    $entry .= "SUBJECT: {$subject}\n";
    $entry .= "BODY:\n{$body}\n";
    $entry .= "NOTE: {$smtpNote}\n";

    @file_put_contents(EMAIL_FALLBACK_LOG_PATH, $entry, FILE_APPEND);

    return [false, "Email not sent. {$smtpNote} Fallback: logged to email outbox."];
}
