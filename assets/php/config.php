<?php
// phpMyAdmin (XAMPP) database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "capstone";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    error_log("Host: $host, User: $username, DB: $database");
    die("Database connection failed. Please check your credentials.");
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>