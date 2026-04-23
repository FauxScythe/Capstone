<?php
require_once 'config.php';
require_once 'ocr_validate.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
session_start();
if (!isset($_SESSION['verification']['verified']) || $_SESSION['verification']['verified'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please verify your email or mobile number before registering.']);
    exit;
}
if (strtotime($_SESSION['verification']['expires']) < time()) {
    unset($_SESSION['verification']);
    echo json_encode(['success' => false, 'message' => 'Verification has expired. Please verify your email or mobile again.']);
    exit;
}
$role = $_POST['role'] ?? '';
function validatePassword(string $password, string $confirm): ?string {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        return 'Password must contain at least 1 capital letter and 1 number.';
    }
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }
    return null;
}
if ($role === 'jobseeker') {
    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $middle_name     = trim($_POST['middle_name'] ?? '');
    $dob             = $_POST['dob'] ?? '';
    $sex             = $_POST['sex'] ?? '';
    $civil_status    = $_POST['civil_status'] ?? '';
    $address         = trim($_POST['address'] ?? '');
    $mobile          = trim($_POST['mobile'] ?? '');
    $email           = strtolower(trim($_POST['email'] ?? ''));
    $education       = $_POST['education'] ?? '';
    $course          = trim($_POST['course'] ?? '');
    $skills          = trim($_POST['skills'] ?? '');
    $experience      = trim($_POST['experience'] ?? '');
    $is_pwd          = isset($_POST['is_pwd']) ? 1 : 0;
    $disability_type = trim($_POST['disability_type'] ?? '');
    $pwd_id          = trim($_POST['pwd_id'] ?? '');
    $accessibility   = trim($_POST['accessibility'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm_pass    = $_POST['confirm_password'] ?? '';
    if (!$first_name || !$last_name || !$dob || !$sex || !$civil_status || !$address || !$mobile || !$email || !$education || !$skills || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    if (!preg_match('/^09\d{9}$/', $mobile)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number must start with 09 and be 11 digits.']);
        exit;
    }
    $pass_err = validatePassword($password, $confirm_pass);
    if ($pass_err) {
        echo json_encode(['success' => false, 'message' => $pass_err]);
        exit;
    }
    if (!isset($_FILES['gov_id']) || $_FILES['gov_id']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please upload a valid government ID.']);
        exit;
    }
    $id_file   = $_FILES['gov_id'];
    $tmp       = $id_file['tmp_name'];
    $dest_path = '';
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $allowed_mime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($allowed_mime[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Please upload a JPG, PNG, or PDF for your ID.']);
        exit;
    }
    if ($id_file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'ID file must be 5MB or less.']);
        exit;
    }
    $upload_dir = __DIR__ . '/uploads/ids/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    error_log("Upload debug - tmp: $tmp, dest: $dest_path, upload_dir writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));
    $ext      = $allowed_mime[$mime];
    $safename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest_path = $upload_dir . $safename;
    if (!move_uploaded_file($tmp, $dest_path)) {
        $upload_error = error_get_last();
        $error_msg = $upload_error ? $upload_error['message'] : 'Unknown error';
        error_log("Upload failed - Error: $error_msg");
        echo json_encode(['success' => false, 'message' => 'Failed to upload ID. Please try again.']);
        exit;
    }
    $gov_id_path = 'uploads/ids/' . $safename;
    $id_hash     = hash_file('sha256', $dest_path);
    $ocr = validateIdWithOCR(['tmp_name' => $dest_path, 'type' => $mime, 'name' => $safename], $first_name, $last_name, $dob);
    if (!$ocr['valid']) {
        @unlink($dest_path);
        echo json_encode(['success' => false, 'message' => $ocr['message']]);
        exit;
    }
    $full_name = "$first_name $last_name";
    $stmt = mysqli_prepare($conn, "SELECT id FROM jobseekers WHERE email = ? OR CONCAT(first_name,' ',last_name) = ? OR id_image_hash = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'sss', $email, $full_name, $id_hash);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        $stmt2 = mysqli_prepare($conn, "SELECT id FROM jobseekers WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, 's', $email);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_store_result($stmt2);
        if (mysqli_stmt_num_rows($stmt2) > 0) {
            @unlink($dest_path);
            echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
            exit;
        }
        mysqli_stmt_close($stmt2);
        $stmt3 = mysqli_prepare($conn, "SELECT id FROM jobseekers WHERE CONCAT(first_name,' ',last_name) = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt3, 's', $full_name);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_store_result($stmt3);
        if (mysqli_stmt_num_rows($stmt3) > 0) {
            @unlink($dest_path);
            echo json_encode(['success' => false, 'message' => 'An account with this full name already exists.']);
            exit;
        }
        mysqli_stmt_close($stmt3);
        @unlink($dest_path);
        echo json_encode(['success' => false, 'message' => 'This ID has already been used for registration.']);
        exit;
    }
    mysqli_stmt_close($stmt);
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn,
        "INSERT INTO jobseekers (first_name, last_name, middle_name, birth_date, sex, civil_status, address, mobile, email,
         education, course, skills, experience, is_pwd, disability_type, pwd_id, accessibility_needs, id_file_path, id_image_hash, id_verification, password)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'verified',?)"
    );
    mysqli_stmt_bind_param($stmt, 'sssssssssssssissssss',
        $first_name, $last_name, $middle_name, $dob, $sex, $civil_status, $address, $mobile, $email,
        $education, $course, $skills, $experience, $is_pwd, $disability_type, $pwd_id, $accessibility, $gov_id_path, $id_hash, $hashed
    );
    if (mysqli_stmt_execute($stmt)) {
        unset($_SESSION['verification']);
        echo json_encode(['success' => true, 'message' => 'Job Seeker account created successfully!']);
    } else {
        @unlink($dest_path);
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
} elseif ($role === 'employer') {
    $company_name     = trim($_POST['company_name'] ?? '');
    $industry         = $_POST['industry'] ?? '';
    $company_size     = $_POST['company_size'] ?? '';
    $business_address = trim($_POST['business_address'] ?? '');
    $contact_name     = trim($_POST['contact_name'] ?? '');
    $designation      = trim($_POST['designation'] ?? '');
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $inclusive_hiring = $_POST['inclusive_hiring'] ?? '';
    $accessibility    = trim($_POST['accessibility'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_pass     = $_POST['confirm_password'] ?? '';
    if (!$company_name || !$industry || !$company_size || !$business_address || !$contact_name || !$designation || !$contact_number || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    if (!preg_match('/^\d{11}$/', $contact_number)) {
        echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits.']);
        exit;
    }
    $pass_err = validatePassword($password, $confirm_pass);
    if ($pass_err) {
        echo json_encode(['success' => false, 'message' => $pass_err]);
        exit;
    }
    $stmt = mysqli_prepare($conn, "SELECT id FROM employers WHERE contact_email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
        exit;
    }
    mysqli_stmt_close($stmt);
    $permit_path = '';
    $dest_path   = '';
    if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/permits/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        error_log("Employer upload debug - tmp: " . $_FILES['business_permit']['tmp_name'] . ", upload_dir writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));
        $ext      = pathinfo($_FILES['business_permit']['name'], PATHINFO_EXTENSION);
        $safename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest_path = $upload_dir . $safename;
        if (move_uploaded_file($_FILES['business_permit']['tmp_name'], $dest_path)) {
            $permit_path = 'uploads/permits/' . $safename;
        } else {
            $upload_error = error_get_last();
            $error_msg = $upload_error ? $upload_error['message'] : 'Unknown error';
            error_log("Employer upload failed - Error: $error_msg");
        }
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn,
        "INSERT INTO employers (company_name, industry, company_size, business_address, contact_person, position,
         contact_number, contact_email, inclusive_hiring, accessibility_features, business_permit, password)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssssssssssss',
        $company_name, $industry, $company_size, $business_address, $contact_name, $designation,
        $contact_number, $email, $inclusive_hiring, $accessibility, $permit_path, $hashed
    );
    if (mysqli_stmt_execute($stmt)) {
        unset($_SESSION['verification']);
        echo json_encode(['success' => true, 'message' => 'Employer account created successfully!']);
    } else {
        if ($dest_path) @unlink($dest_path);
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid role.']);
}
mysqli_close($conn);
?>