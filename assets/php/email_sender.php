<?php
/**
 * Simple email sender for verification codes
 * Uses PHP's built-in mail() function
 */

function sendVerificationEmail($email, $code) {
    $subject = 'SkillBridge AI - Verification Code';
    $message = "
    <html>
    <head>
        <title>SkillBridge AI Verification Code</title>
    </head>
    <body>
        <h2>SkillBridge AI - Email Verification</h2>
        <p>Your verification code is:</p>
        <h1 style='font-size: 32px; color: #667eea; background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px;'>" . $code . "</h1>
        <p>This code will expire in 10 minutes.</p>
        <p>If you didn't request this code, please ignore this email.</p>
        <hr>
        <p style='color: #666; font-size: 12px;'>
            This is an automated message from SkillBridge AI - PESO Bago City<br>
            Inclusive Job Matching Platform
        </p>
    </body>
    </html>
    ";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@skillbridge.test" . "\r\n";
    
    // Send email
    $sent = mail($email, $subject, $message, $headers);
    
    return $sent;
}

function sendVerificationSMS($mobile, $code) {
    // For SMS, you would typically use an SMS API service
    // For now, we'll just log it
    error_log("SMS verification code for $mobile: $code");
    return true; // Simulate successful sending
}
?>
