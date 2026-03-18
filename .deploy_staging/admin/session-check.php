<?php
session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$isAdmin = isset($_SESSION["role"]) && $_SESSION["role"] === "admin";
echo json_encode(["authenticated" => $isAdmin]);
exit();
?>
