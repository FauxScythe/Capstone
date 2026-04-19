<?php
require_once 'email_sender_real.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'send_code':
        sendVerificationCode();
        break;
    case 'verify_code':
        verifyCode();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
}

function sendVerificationCode() {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $mobile = trim($_POST['mobile'] ?? '');
    
    if (!$email && !$mobile) {
        echo json_encode(['success' => false, 'message' => 'Please provide an email or mobile number.']);
        exit;
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
        exit;
    }
    
    $db_check = true;
    $conn = null;
    
    try {
        require_once 'config.php';
        global $conn;
        
        if ($conn && mysqli_ping($conn)) {
            if ($email) {
                $stmt = mysqli_prepare($conn, "SELECT id FROM jobseekers WHERE email = ? UNION SELECT id FROM employers WHERE contact_email = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'ss', $email, $email);
            } else {
                $stmt = mysqli_prepare($conn, "SELECT id FROM jobseekers WHERE mobile = ? UNION SELECT id FROM employers WHERE contact_number = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'ss', $mobile, $mobile);
            }
            
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                echo json_encode(['success' => false, 'message' => 'This email or mobile number is already registered.']);
                mysqli_stmt_close($stmt);
                exit;
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $db_check = false;
        }
        
    } catch (Exception $e) {
        error_log("Database connection failed in email verifier: " . $e->getMessage());
        $db_check = false;
        $conn = null;
    }
    
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 600);
    
    session_start();
    $_SESSION['verification'] = [
        'code' => $code,
        'email' => $email,
        'mobile' => $mobile,
        'expires' => $expires,
        'attempts' => 0
    ];
    
    $sent = false;
    $message = '';
    
    if ($email) {
        try {
            $sent = sendVerificationEmail($email, $code);
            if ($sent) {
                $message = "Verification code sent to $email. Please check your inbox (and spam folder).";
            } else {
                $message = "Failed to send email. Please check your email configuration.";
            }
        } catch (Exception $e) {
            $message = "Email error: " . $e->getMessage();
        }
    } elseif ($mobile) {
        $sent = sendVerificationSMS($mobile, $code);
        $message = "Verification code sent to $mobile. For development, code: $code";
    }
    
    error_log("Verification code for $email/$mobile: $code");
    
    if ($conn) {
        @mysqli_close($conn);
    }
    
    if ($sent || $mobile) {
        echo json_encode([
            'success' => true, 
            'message' => $message . ($db_check ? '' : ' (Database check skipped)'),
            'expires_in' => 600
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $message]);
    }
}

function verifyCode() {
    session_start();
    
    $input_code = $_POST['code'] ?? '';
    
    if (!isset($_SESSION['verification'])) {
        echo json_encode(['success' => false, 'message' => 'No verification session found. Please request a new code.']);
        exit;
    }
    
    $verification = $_SESSION['verification'];
    
    if (strtotime($verification['expires']) < time()) {
        unset($_SESSION['verification']);
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
        exit;
    }
    
    if ($verification['attempts'] >= 3) {
        unset($_SESSION['verification']);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new code.']);
        exit;
    }
    
    if ($input_code === $verification['code']) {
        $_SESSION['verification']['verified'] = true;
        echo json_encode([
            'success' => true, 
            'message' => 'Email/mobile verified successfully! You can now proceed with registration.'
        ]);
    } else {
        $_SESSION['verification']['attempts']++;
        $remaining = 3 - $_SESSION['verification']['attempts'];
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid verification code. ' . ($remaining > 0 ? "$remaining attempts remaining." : 'Please request a new code.')
        ]);
    }
}
?>
