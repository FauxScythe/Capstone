<?php
$config = require __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

function sendVerificationEmail($email, $code) {
    global $config;
    
    $mail = new PHPMailer(true);
    
    try {
        if ($config['debug']) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        } else {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
        }
        
        $mail->isSMTP();
        $mail->Host       = $config['email']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['email']['username'];
        $mail->Password   = $config['email']['password'];
        $mail->SMTPSecure = $config['email']['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['email']['port'];
        
        $mail->setFrom($config['email']['from_email'], $config['email']['from_name']);
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = 'SkillBridge AI - Email Verification Code';
        
        $mail->Body = '
        <html>
        <head>
            <title>SkillBridge AI Verification Code</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .code-box { background: white; border: 3px solid #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 36px; font-weight: bold; color: #667eea; letter-spacing: 5px; margin: 10px 0; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
                .logo { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">🤖 SkillBridge AI</div>
                    <h1>Email Verification</h1>
                    <p>PESO Bago City · Inclusive Job Matching Platform</p>
                </div>
                <div class="content">
                    <h2>Verify Your Email Address</h2>
                    <p>Thank you for registering with SkillBridge AI! Please use the verification code below to complete your registration:</p>
                    
                    <div class="code-box">
                        <p style="margin: 0; color: #666; font-size: 14px;">Your verification code is:</p>
                        <div class="code">' . $code . '</div>
                        <p style="margin: 10px 0 0 0; color: #666; font-size: 12px;">This code will expire in 10 minutes</p>
                    </div>
                    
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>Never share this code with anyone</li>
                        <li>This code can only be used once</li>
                        <li>If you didn\'t request this code, please ignore this email</li>
                    </ul>
                    
                    <p>If you have any questions, please contact our support team.</p>
                    
                    <div class="footer">
                        <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                        <p><strong>SkillBridge AI</strong><br>
                        PESO Bago City Employment Office<br>
                        Inclusive Job Matching Platform<br>
                        <em>In compliance with the Data Privacy Act of 2012</em></p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "SkillBridge AI - Verification Code: $code. This code will expire in 10 minutes. Please do not share this code with anyone.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

function sendVerificationSMS($mobile, $code) {
    error_log("SMS verification code for $mobile: $code");
    return true;
}
?>
