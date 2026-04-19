<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../templates/login.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_data = null;
$applications = [];
$error = '';
$success = '';

function calculateAge($birthday) {
    if (empty($birthday)) {
        return null;
    }

    try {
        $dob = new DateTime($birthday);
        $today = new DateTime('today');
        if ($dob > $today) {
            return null;
        }
        return $dob->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}

try {
    // Get user data based on role
    if ($user_role === 'jobseeker') {
        $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, email, mobile, birth_date, address, barangay, city, disability_type, id_verification_status, id_type, id_image_path, created_at FROM jobseekers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        
        // Get job applications
        $stmt = mysqli_prepare($conn, "SELECT ja.*, j.title, j.company_name, j.status as job_status FROM job_applications ja LEFT JOIN job_postings j ON ja.job_id = j.id WHERE ja.jobseeker_id = ? ORDER BY ja.application_date DESC");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $applications = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
    } elseif ($user_role === 'employer') {
        $stmt = mysqli_prepare($conn, "SELECT id, company_name, contact_person, email, mobile, business_address, industry, created_at FROM employers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        
        // Get job postings
        $stmt = mysqli_prepare($conn, "SELECT * FROM job_postings WHERE employer_id = ? ORDER BY posted_date DESC");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $applications = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        
    } elseif ($user_role === 'peso_officer') {
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, position, office_address, created_at FROM peso_officers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        
        // Get statistics
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total_seekers FROM jobseekers");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_seekers = mysqli_fetch_assoc($result)['total_seekers'];
        
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total_employers FROM employers");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_employers = mysqli_fetch_assoc($result)['total_employers'];
        
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total_postings FROM job_postings");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_postings = mysqli_fetch_assoc($result)['total_postings'];
        
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total_applications FROM job_applications");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_applications = mysqli_fetch_assoc($result)['total_applications'];
    }
    
} catch (Exception $e) {
    $error = 'Error loading dashboard: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if ($user_role === 'jobseeker') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($mobile)) {
            $error = 'Required fields are missing.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^(09\d{9})$/', preg_replace('/[\s\-\(\)]/', '', $mobile))) {
            $error = 'Please enter a valid Philippine mobile number.';
        } else {
            try {
                // Update jobseeker profile
                $stmt = mysqli_prepare($conn, "UPDATE jobseekers SET first_name = ?, last_name = ?, email = ?, mobile = ?, address = ?, barangay = ?, city = ?, birth_date = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssssssssi", $first_name, $last_name, $email, $mobile, $address, $barangay, $city, $birth_date, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, email, mobile, birth_date, address, barangay, city, disability_type, id_verification_status, id_type, id_image_path, created_at FROM jobseekers WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user_data = mysqli_fetch_assoc($result);
                } else {
                    $error = 'Failed to update profile.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($user_role === 'employer') {
        $company_name = trim($_POST['company_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $business_address = trim($_POST['business_address'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        
        if (empty($company_name) || empty($contact_person) || empty($email) || empty($mobile)) {
            $error = 'Required fields are missing.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Update employer profile
                $stmt = mysqli_prepare($conn, "UPDATE employers SET company_name = ?, contact_person = ?, contact_email = ?, contact_number = ?, business_address = ?, industry = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssssssi", $company_name, $contact_person, $email, $mobile, $business_address, $industry, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $stmt = mysqli_prepare($conn, "SELECT id, company_name, contact_person, contact_email, contact_number, business_address, industry, created_at FROM employers WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user_data = mysqli_fetch_assoc($result);
                } else {
                    $error = 'Failed to update profile.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Determine dashboard template based on role
$dashboard_template = '';
switch ($user_role) {
    case 'jobseeker':
        $dashboard_template = '../templates/jobseeker.html';
        break;
    case 'employer':
        $dashboard_template = '../templates/employer.html';
        break;
    case 'peso_officer':
        $dashboard_template = '../templates/peso_officer.html';
        break;
    default:
        header('Location: ../templates/login.html');
        exit;
}

// Redirect to the appropriate dashboard template
header('Location: ' . $dashboard_template);
exit;
?>
