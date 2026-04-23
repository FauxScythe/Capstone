<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "capstone";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please check your credentials.");
}
$conn->set_charset("utf8mb4");
?>