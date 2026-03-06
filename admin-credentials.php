<?php

function get_admin_credential_path(): string {
    return __DIR__ . DIRECTORY_SEPARATOR . "admin_credentials.json";
}

function get_default_admin_credentials(): array {
    return [
        "username" => "admin",
        "email" => "admin@geck.com",
        "password_hash" => password_hash("admin@geck", PASSWORD_DEFAULT)
    ];
}

function get_admin_credentials(): array {
    $path = get_admin_credential_path();

    if (!file_exists($path)) {
        $defaults = get_default_admin_credentials();
        @file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }

    $raw = @file_get_contents($path);
    $decoded = json_decode($raw ?: "", true);

    if (!is_array($decoded)) {
        $defaults = get_default_admin_credentials();
        @file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }

    if (!isset($decoded["username"], $decoded["email"], $decoded["password_hash"])) {
        $defaults = get_default_admin_credentials();
        @file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }

    return $decoded;
}

function save_admin_credentials(array $creds): bool {
    $path = get_admin_credential_path();
    $json = json_encode($creds, JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($path, $json) !== false;
}
