<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employer') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Employers only.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'post_job':
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $slots = intval($_POST['slots'] ?? 1);
        $employment_type = trim($_POST['employment_type'] ?? '');
        $required_skills = trim($_POST['required_skills'] ?? '');
        $pwd_friendly = intval($_POST['pwd_friendly'] ?? 0);
        $accessibility = trim($_POST['accessibility'] ?? '');
        $employer_id = $_SESSION['user_id'];
        
        // Validation
        if (empty($title) || empty($description) || empty($employment_type) || empty($required_skills)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }
        
        $stmt = mysqli_prepare($conn,
            "INSERT INTO job_postings
                (employer_id, title, description, slots, employment_type, required_skills, pwd_friendly, accessibility, status, posted_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())"
        );
        mysqli_stmt_bind_param($stmt, 'ississis',
            $employer_id, $title, $description, $slots,
            $employment_type, $required_skills, $pwd_friendly, $accessibility
        );
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Job posted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_stmt_error($stmt)]);
        }
        mysqli_stmt_close($stmt);
        break;
        
    case 'get_jobs':
        $employer_id = $_SESSION['user_id'];
        $stmt = mysqli_prepare($conn, "SELECT id, title, description, slots, employment_type, required_skills, pwd_friendly, accessibility, status, posted_date FROM job_postings WHERE employer_id = ? ORDER BY posted_date DESC");
        mysqli_stmt_bind_param($stmt, 'i', $employer_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $jobs = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $jobs[] = $row;
        }
        
        echo json_encode(['success' => true, 'jobs' => $jobs]);
        mysqli_stmt_close($stmt);
        break;
        
    case 'delete_job':
        $job_id = intval($_POST['job_id'] ?? 0);
        $employer_id = $_SESSION['user_id'];
        
        // Verify job belongs to this employer
        $stmt = mysqli_prepare($conn, "SELECT id FROM job_postings WHERE id = ? AND employer_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $job_id, $employer_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 0) {
            echo json_encode(['success' => false, 'message' => 'Job not found or access denied.']);
            mysqli_stmt_close($stmt);
            exit;
        }
        mysqli_stmt_close($stmt);
        
        // Delete job posting
        $stmt = mysqli_prepare($conn, "DELETE FROM job_postings WHERE id = ? AND employer_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $job_id, $employer_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Job deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete job. Please try again.']);
        }
        mysqli_stmt_close($stmt);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

mysqli_close($conn);
?>
