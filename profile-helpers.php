<?php

function placementhub_default_profile_photo(): string
{
    return "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";
}

function placementhub_media_url(?string $relativePath, string $prefix = ""): string
{
    $path = trim((string)$relativePath);
    if ($path === "") {
        return placementhub_default_profile_photo();
    }

    return $prefix . ltrim($path, "/");
}

function placementhub_student_photo_url(array $student, string $prefix = ""): string
{
    return placementhub_media_url((string)($student["profile_photo_path"] ?? ""), $prefix);
}

function placementhub_company_photo_url(array $company, string $prefix = ""): string
{
    return placementhub_media_url((string)($company["profile_photo_path"] ?? ""), $prefix);
}

function placementhub_admin_photo_url(array $profile, string $prefix = ""): string
{
    return placementhub_media_url((string)($profile["profile_photo_path"] ?? ""), $prefix);
}
?>
