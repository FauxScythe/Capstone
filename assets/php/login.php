<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
$email_or_mobile = trim($_POST['email_or_mobile'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'jobseeker';
if (!$email_or_mobile || !$password) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}
$is_email  = filter_var($email_or_mobile, FILTER_VALIDATE_EMAIL);
$is_mobile = preg_match('/^09\d{9}$/', $email_or_mobile);
if (!$is_email && !$is_mobile) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address or mobile number.']);
    exit;
}
switch ($role) {
    case 'jobseeker':
        $stmt = $is_email
            ? mysqli_prepare($conn, "SELECT id, first_name, last_name, email, mobile, password FROM jobseekers WHERE email = ? LIMIT 1")
            : mysqli_prepare($conn, "SELECT id, first_name, last_name, email, mobile, password FROM jobseekers WHERE mobile = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email_or_mobile);
        break;
    case 'employer':
        $stmt = $is_email
            ? mysqli_prepare($conn, "SELECT id, company_name, contact_person, contact_email, contact_number, password FROM employers WHERE contact_email = ? LIMIT 1")
            : mysqli_prepare($conn, "SELECT id, company_name, contact_person, contact_email, contact_number, password FROM employers WHERE contact_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email_or_mobile);
        break;
    case 'officer':
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, password FROM peso_officers WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email_or_mobile);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
        exit;
}
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $row['password'])) {
        $_SESSION['user_id']   = $row['id'];
        $_SESSION['user_role'] = $role;
        $_SESSION['logged_in'] = true;
        switch ($role) {
            case 'jobseeker':
                $_SESSION['user_name']   = $row['first_name'] . ' ' . $row['last_name'];
                $_SESSION['user_email']  = $row['email'];
                $_SESSION['user_mobile'] = $row['mobile'];
                $redirect = '../templates/jobseeker.html';
                break;
            case 'employer':
                $_SESSION['user_name']    = $row['company_name'];
                $_SESSION['user_contact'] = $row['contact_person'];
                $_SESSION['user_email']   = $row['contact_email'];
                $_SESSION['user_mobile']  = $row['contact_number'];
                $redirect = '../templates/employer.html';
                break;
            case 'officer':
                $_SESSION['user_name']  = $row['name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_role']  = 'peso_officer';
                $redirect = '../templates/peso_officer.html';
                break;
        }
        echo json_encode(['success' => true, 'message' => 'Login successful!', 'redirect' => $redirect, 'user' => ['name' => $_SESSION['user_name'], 'role' => $role]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No account found with this email/mobile number.']);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>