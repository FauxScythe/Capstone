<?php
require_once 'assets/php/config.php';

// Ensure peso_officers table exists with correct columns
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS peso_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    office VARCHAR(100),
    position VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$name     = 'PESO Officer';
$email    = 'officer@peso.gov.ph';
$office   = 'PESO Bago City';
$position = 'Employment Officer';
$password = 'Officer1';
$hashed   = password_hash($password, PASSWORD_DEFAULT);

// Check if already exists
$check = mysqli_prepare($conn, "SELECT id FROM peso_officers WHERE email = ?");
mysqli_stmt_bind_param($check, 's', $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    echo "
    <div style='font-family:sans-serif;max-width:420px;margin:60px auto;padding:32px;border:1px solid #f0c040;border-radius:12px;background:#fffdf0;'>
        <h2 style='color:#856404;'>⚠️ Account already exists</h2>
        <p style='margin-top:8px;font-size:14px;color:#555;'>An officer account with <strong>$email</strong> already exists.</p>
        <a href='assets/templates/login.html' style='display:inline-block;margin-top:16px;padding:10px 22px;background:#1b2a4a;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;'>Go to Login →</a>
    </div>";
} else {
    mysqli_stmt_close($check);

    $stmt = mysqli_prepare($conn,
        "INSERT INTO peso_officers (name, email, password, office, position) VALUES (?,?,?,?,?)"
    );
    mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hashed, $office, $position);

    if (mysqli_stmt_execute($stmt)) {
        echo "
        <div style='font-family:sans-serif;max-width:420px;margin:60px auto;padding:32px;border:1px solid #e5e5e5;border-radius:12px;'>
            <h2 style='color:#1b2a4a;margin-bottom:16px;'>✅ PESO Officer account created</h2>
            <table style='width:100%;font-size:14px;border-collapse:collapse;'>
                <tr><td style='padding:6px 0;color:#777;width:110px;'>Email</td><td><strong>$email</strong></td></tr>
                <tr><td style='padding:6px 0;color:#777;'>Password</td><td><strong>$password</strong></td></tr>
                <tr><td style='padding:6px 0;color:#777;'>Name</td><td>$name</td></tr>
                <tr><td style='padding:6px 0;color:#777;'>Office</td><td>$office</td></tr>
                <tr><td style='padding:6px 0;color:#777;'>Position</td><td>$position</td></tr>
                <tr><td style='padding:6px 0;color:#777;'>Role tab</td><td><strong>PESO Officer</strong></td></tr>
            </table>
            <a href='assets/templates/login.html' style='display:inline-block;margin-top:20px;padding:10px 22px;background:#c0392b;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;'>Go to Login →</a>
            <p style='margin-top:16px;font-size:12px;color:#aaa;'>Delete this file after logging in.</p>
        </div>";
    } else {
        echo "<p style='font-family:sans-serif;color:red;'>Error: " . mysqli_error($conn) . "</p>";
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
?>
