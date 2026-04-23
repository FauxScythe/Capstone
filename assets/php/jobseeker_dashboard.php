<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'jobseeker') {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
if ($action === 'get_jobs') {
    $result = mysqli_query($conn,
        "SELECT jp.id, jp.title, jp.description, jp.slots, jp.employment_type,
                jp.required_skills, jp.pwd_friendly, jp.accessibility,
                jp.status, jp.posted_date,
                e.company_name
         FROM job_postings jp
         JOIN employers e ON e.id = jp.employer_id
         WHERE jp.status = 'active'
         ORDER BY jp.posted_date DESC"
    );
    if (!$result) {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        exit;
    }
    echo json_encode(['success' => true, 'jobs' => mysqli_fetch_all($result, MYSQLI_ASSOC)]);
    exit;
}
if ($action === 'apply_job') {
    $job_id = (int)($_POST['job_id'] ?? 0);
    if (!$job_id) { echo json_encode(['success' => false, 'message' => 'Invalid job.']); exit; }
    $chk = mysqli_prepare($conn, "SELECT resume_status FROM jobseekers WHERE id=?");
    mysqli_stmt_bind_param($chk, 'i', $user_id);
    mysqli_stmt_execute($chk);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);
    $resume_status = $row['resume_status'] ?? 'pending';
    if ($resume_status !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Your resume has not been approved by a PESO Officer yet. Please wait for approval before applying.']);
        exit;
    }
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS job_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        jobseeker_id INT NOT NULL,
        status ENUM('pending','reviewed','interview','hired','rejected') DEFAULT 'pending',
        applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
        FOREIGN KEY (jobseeker_id) REFERENCES jobseekers(id) ON DELETE CASCADE
    )");
    $chk = mysqli_prepare($conn, "SELECT id FROM job_applications WHERE job_id=? AND jobseeker_id=?");
    mysqli_stmt_bind_param($chk, 'ii', $job_id, $user_id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) > 0) {
        mysqli_stmt_close($chk);
        echo json_encode(['success' => false, 'message' => 'You have already applied for this job.']);
        exit;
    }
    mysqli_stmt_close($chk);
    $stmt = mysqli_prepare($conn, "INSERT INTO job_applications (job_id, jobseeker_id) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt, 'ii', $job_id, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Application submitted.']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}
if ($action === 'get_applications') {
    $stmt = mysqli_prepare($conn,
        "SELECT ja.id, ja.status, ja.applied_date,
                jp.title, jp.employment_type, jp.required_skills, jp.slots,
                e.company_name
         FROM job_applications ja
         JOIN job_postings jp ON jp.id = ja.job_id
         JOIN employers e ON e.id = jp.employer_id
         WHERE ja.jobseeker_id = ?
         ORDER BY ja.applied_date DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'applications' => $rows]);
    exit;
}
if ($action === 'upload_portfolio') {
    if (empty($_FILES['portfolio']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'No files received.']);
        exit;
    }
    mysqli_query($conn,
        "CREATE TABLE IF NOT EXISTS jobseeker_portfolio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            jobseeker_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (jobseeker_id) REFERENCES jobseekers(id) ON DELETE CASCADE
        )"
    );
    $upload_dir = __DIR__ . '/uploads/portfolio/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $allowed_mime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $uploaded = 0;
    $errors   = [];
    $files = $_FILES['portfolio'];
    $count = count($files['name']);
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM jobseeker_portfolio WHERE jobseeker_id = $user_id");
    $existing = (int)mysqli_fetch_assoc($r)['c'];
    if ($existing >= 10) {
        echo json_encode(['success' => false, 'message' => 'Maximum 10 files already uploaded.']);
        exit;
    }
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > 5 * 1024 * 1024) { $errors[] = $files['name'][$i] . ' exceeds 5MB.'; continue; }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $files['tmp_name'][$i]);
        finfo_close($finfo);
        if (!isset($allowed_mime[$mime])) { $errors[] = $files['name'][$i] . ': invalid type.'; continue; }
        $ext      = $allowed_mime[$mime];
        $safename = 'portfolio_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = $upload_dir . $safename;
        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $path  = 'uploads/portfolio/' . $safename;
            $oname = basename($files['name'][$i]);
            $stmt  = mysqli_prepare($conn, "INSERT INTO jobseeker_portfolio (jobseeker_id, file_path, original_name) VALUES (?,?,?)");
            mysqli_stmt_bind_param($stmt, 'iss', $user_id, $path, $oname);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $uploaded++;
        }
        if ($existing + $uploaded >= 10) break;
    }
    echo json_encode([
        'success' => $uploaded > 0,
        'message' => $uploaded > 0 ? "$uploaded file(s) uploaded." : 'No files uploaded.',
        'errors'  => $errors
    ]);
    exit;
}
if ($action === 'get_portfolio') {
    $stmt = mysqli_prepare($conn, "SELECT id, file_path, original_name, uploaded_at FROM jobseeker_portfolio WHERE jobseeker_id = ? ORDER BY uploaded_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'files' => $rows]);
    exit;
}
if ($action === 'delete_portfolio') {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT file_path FROM jobseeker_portfolio WHERE id = ? AND jobseeker_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $file_id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) { echo json_encode(['success' => false, 'message' => 'File not found.']); exit; }
    @unlink(__DIR__ . '/' . $row['file_path']);
    $stmt = mysqli_prepare($conn, "DELETE FROM jobseeker_portfolio WHERE id = ? AND jobseeker_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $file_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}
if ($action === 'update_profile') {
    $first_name   = trim($_POST['first_name']   ?? '');
    $middle_name  = trim($_POST['middle_name']  ?? '');
    $last_name    = trim($_POST['last_name']    ?? '');
    $email        = strtolower(trim($_POST['email']  ?? ''));
    $mobile       = trim($_POST['mobile']       ?? '');
    $birth_date   = trim($_POST['birth_date']   ?? '');
    $sex          = trim($_POST['sex']          ?? '');
    $civil_status = trim($_POST['civil_status'] ?? '');
    $address      = trim($_POST['address']      ?? '');
    if (!$first_name || !$last_name || !$email || !$mobile) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
        exit;
    }
    $stmt = mysqli_prepare($conn,
        "UPDATE jobseekers SET first_name=?, middle_name=?, last_name=?, email=?, mobile=?,
         birth_date=?, sex=?, civil_status=?, address=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'sssssssssi',
        $first_name, $middle_name, $last_name, $email, $mobile,
        $birth_date, $sex, $civil_status, $address, $user_id
    );
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Profile saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}
if (($_POST['action'] ?? '') === 'update_resume') {
    $education       = trim($_POST['education'] ?? '');
    $course          = trim($_POST['course'] ?? '');
    $experience      = trim($_POST['experience'] ?? '');
    $skills          = trim($_POST['skills'] ?? '');
    $disability_type = trim($_POST['disability_type'] ?? '');
    $accessibility   = trim($_POST['accessibility'] ?? '');
    $first_time      = (int)($_POST['first_time_worker'] ?? 0);
    $stmt = mysqli_prepare($conn,
        "UPDATE jobseekers SET education=?, course=?, experience=?, skills=?, disability_type=?, accessibility_needs=?, first_time_worker=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssssii', $education, $course, $experience, $skills, $disability_type, $accessibility, $first_time, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Resume saved.']);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}
$col = mysqli_query($conn, "SHOW COLUMNS FROM jobseekers LIKE 'first_time_worker'");
if (mysqli_num_rows($col) === 0) {
    mysqli_query($conn, "ALTER TABLE jobseekers ADD COLUMN first_time_worker TINYINT(1) DEFAULT 0");
}
$stmt = mysqli_prepare($conn, "SELECT first_name, last_name, middle_name, email, mobile, birth_date, sex, civil_status, address, disability_type, accessibility_needs, education, course, skills, experience, resume_status, COALESCE(first_time_worker,0) as first_time_worker FROM jobseekers WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'user' => [
            'name'            => $row['first_name'] . ' ' . $row['last_name'],
            'first_name'      => $row['first_name'],
            'middle_name'     => $row['middle_name'],
            'last_name'       => $row['last_name'],
            'email'           => $row['email'],
            'mobile'          => $row['mobile'],
            'birth_date'      => $row['birth_date'],
            'sex'             => $row['sex'],
            'civil_status'    => $row['civil_status'],
            'address'         => $row['address'],
            'disability_type'    => $row['disability_type'],
            'accessibility_needs'=> $row['accessibility_needs'],
            'education'          => $row['education'],
            'course'          => $row['course'],
            'skills'          => $row['skills'],
            'experience'         => $row['experience'],
            'resume_status'      => $row['resume_status'] ?? 'pending',
            'first_time_worker'  => $row['first_time_worker'] ?? 0,
            'role'               => 'jobseeker'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
}
mysqli_stmt_close($stmt);
?>