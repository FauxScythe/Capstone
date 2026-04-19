<?php
// Database Connection Test
echo "<h2>Database Connection Test</h2>";

// Test 1: Try with root user (XAMPP default)
echo "<h3>Test 1: Root User (XAMMP Default)</h3>";
$host = "localhost";
$username = "root";
$password = "";
$database = "u520834156_dbRidgeAI2026";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo "<p style='color: red;'>Root connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>Root connection successful!</p>";
    $conn->close();
}

// Test 2: Try your user credentials
echo "<h3>Test 2: Your User Credentials</h3>";
$username = "u520834156_userSkillBrgde";
$password = ";MXakHXu3fI";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo "<p style='color: red;'>Your user connection failed: " . $conn->connect_error . "</p>";
    echo "<p><strong>Error details:</strong><br>";
    echo "Host: $host<br>";
    echo "User: $username<br>";
    echo "Password: " . (empty($password) ? "(empty)" : "has password") . "<br>";
    echo "Database: $database</p>";
} else {
    echo "<p style='color: green;'>Your user connection successful!</p>";
    $conn->close();
}

// Test 3: List available databases (with root)
echo "<h3>Test 3: Available Databases</h3>";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    echo "<p style='color: red;'>Cannot connect to list databases</p>";
} else {
    $result = $conn->query("SHOW DATABASES");
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
    $conn->close();
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If Test 1 works but Test 2 fails, your user credentials are wrong</li>";
echo "<li>If Test 1 fails, XAMPP MySQL service may not be running</li>";
echo "<li>If Test 3 doesn't show your database, it doesn't exist</li>";
echo "</ol>";
?>
