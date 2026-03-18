<?php
require_once __DIR__ . "/db-config.php";
$cfg = placementhub_db_config();
$conn = new mysqli($cfg["host"], $cfg["user"], $cfg["pass"], $cfg["name"]);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
