<?php

function get_admin_credential_path(): string {
    return __DIR__ . DIRECTORY_SEPARATOR . "admin_credentials.json";
}

function get_default_admin_credentials(): array {
    return [
        "username" => "admin",
        "email" => "admin@geck.com",
        "password_hash" => password_hash("admin@geck", PASSWORD_DEFAULT),
        "profile" => [
            "name" => "Admin User",
            "email" => "admin@geck.com",
            "role_title" => "Placement Coordinator",
            "department" => "Placement Cell",
            "phone" => "+91 9876543210"
        ],
        "team_members" => [
            ["name" => "Dr. John Smith", "role" => "Head Coordinator", "mobile" => "+91 9876543211"],
            ["name" => "Sarah Johnson", "role" => "Assistant Coordinator", "mobile" => "+91 9876543212"],
            ["name" => "Emily Davis", "role" => "Industry Liaison", "mobile" => "+91 9876543213"],
            ["name" => "David Wilson", "role" => "Student Relations", "mobile" => "+91 9876543214"]
        ]
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

    if (!isset($decoded["profile"]) || !is_array($decoded["profile"])) {
        $defaults = get_default_admin_credentials();
        $decoded["profile"] = $defaults["profile"];
    } else {
        $defaults = get_default_admin_credentials();
        $decoded["profile"] = array_merge($defaults["profile"], $decoded["profile"]);
    }

    $defaultTeam = get_default_admin_credentials()["team_members"];
    if (!isset($decoded["team_members"]) || !is_array($decoded["team_members"])) {
        $decoded["team_members"] = $defaultTeam;
    } else {
        $normalizedTeam = [];
        foreach ($decoded["team_members"] as $member) {
            if (!is_array($member)) {
                continue;
            }
            $memberName = trim((string)($member["name"] ?? ""));
            $memberRole = trim((string)($member["role"] ?? ""));
            $memberMobile = trim((string)($member["mobile"] ?? ""));
            if ($memberName === "" && $memberRole === "" && $memberMobile === "") {
                continue;
            }
            $normalizedTeam[] = [
                "name" => $memberName,
                "role" => $memberRole,
                "mobile" => $memberMobile
            ];
        }
        $decoded["team_members"] = count($normalizedTeam) > 0 ? $normalizedTeam : $defaultTeam;
    }

    return $decoded;
}

function save_admin_credentials(array $creds): bool {
    $path = get_admin_credential_path();
    $json = json_encode($creds, JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($path, $json) !== false;
}

function get_admin_profile(): array {
    $creds = get_admin_credentials();
    $defaults = get_default_admin_credentials()["profile"];
    $profile = is_array($creds["profile"] ?? null) ? $creds["profile"] : [];
    return array_merge($defaults, $profile);
}

function save_admin_profile(array $profile): bool {
    $creds = get_admin_credentials();
    $defaults = get_default_admin_credentials()["profile"];
    $creds["profile"] = array_merge($defaults, $profile);
    $creds["email"] = $creds["profile"]["email"];
    return save_admin_credentials($creds);
}

function get_admin_team_members(): array {
    $creds = get_admin_credentials();
    $defaultTeam = get_default_admin_credentials()["team_members"];
    $members = is_array($creds["team_members"] ?? null) ? $creds["team_members"] : [];

    $normalized = [];
    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }
        $name = trim((string)($member["name"] ?? ""));
        $role = trim((string)($member["role"] ?? ""));
        $mobile = trim((string)($member["mobile"] ?? ""));
        if ($name === "" && $role === "" && $mobile === "") {
            continue;
        }
        $normalized[] = ["name" => $name, "role" => $role, "mobile" => $mobile];
    }

    return count($normalized) > 0 ? $normalized : $defaultTeam;
}

function save_admin_team_members(array $members): bool {
    $creds = get_admin_credentials();
    $normalized = [];
    foreach ($members as $member) {
        if (!is_array($member)) {
            continue;
        }
        $name = trim((string)($member["name"] ?? ""));
        $role = trim((string)($member["role"] ?? ""));
        $mobile = trim((string)($member["mobile"] ?? ""));
        if ($name === "" && $role === "" && $mobile === "") {
            continue;
        }
        $normalized[] = ["name" => $name, "role" => $role, "mobile" => $mobile];
    }

    if (count($normalized) === 0) {
        return false;
    }

    $creds["team_members"] = $normalized;
    return save_admin_credentials($creds);
}
