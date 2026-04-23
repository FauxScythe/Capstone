<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employer') {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}
$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';
if ($action === 'update_profile') {
    $company_name    = trim($_POST['company_name']          ?? '');
    $contact_person  = trim($_POST['contact_person']        ?? '');
    $contact_number  = trim($_POST['contact_number']        ?? '');
    $contact_email   = trim($_POST['contact_email']         ?? '');
    $business_address= trim($_POST['business_address']      ?? '');
    $industry        = trim($_POST['industry']              ?? '');
    $company_size    = trim($_POST['company_size']          ?? '');
    $accessibility   = trim($_POST['accessibility_features']?? '');
    $inclusive       = trim($_POST['inclusive_hiring']      ?? '');
    if (!$company_name || !$contact_email) {
        echo json_encode(['success' => false, 'message' => 'Company name and email are required.']);
        exit;
    }
    $stmt = mysqli_prepare($conn,
        "UPDATE employers SET company_name=?, contact_person=?, contact_name=?,
         contact_number=?, contact_email=?,
         business_address=?, industry=?, company_size=?, accessibility_features=?, inclusive_hiring=?
         WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssssssssi',
        $company_name, $contact_person, $contact_person, $contact_number, $contact_email,
        $business_address, $industry, $company_size, $accessibility, $inclusive, $user_id
    );
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['user_name'] = $company_name;
        echo json_encode(['success' => true, 'message' => 'Profile saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}
if ($action === 'change_password') {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password']     ?? '';
    if (strlen($new) < 8 || !preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $new)) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters with 1 uppercase and 1 number.']);
        exit;
    }
    $stmt = mysqli_prepare($conn, "SELECT password FROM employers WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row || !password_verify($cur, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE employers SET password=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'si', $hashed, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}
$stmt = mysqli_prepare($conn,
    "SELECT company_name,
            COALESCE(NULLIF(contact_person,''), contact_name) as contact_person,
            contact_email, contact_number,
            business_address, business_file_path as business_permit,
            industry, company_size, position, accessibility_features, inclusive_hiring
     FROM employers WHERE id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if ($row) {
    echo json_encode([
        'success' => true,
        'user' => [
            'company_name'          => $row['company_name'],
            'contact_person'        => $row['contact_person'],
            'email'                 => $row['contact_email'],
            'phone'                 => $row['contact_number'],
            'address'               => $row['business_address'],
            'business_permit'       => $row['business_permit'],
            'industry'              => $row['industry'],
            'company_size'          => $row['company_size'],
            'position'              => $row['position'],
            'accessibility_features'=> $row['accessibility_features'],
            'inclusive_hiring'      => $row['inclusive_hiring'],
            'role'                  => 'employer'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Employer not found.']);
}
?>