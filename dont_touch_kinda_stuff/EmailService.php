<?php
/**
 * Email Service using PHPMailer
 * 
 * Handles all email sending for InternHub
 * Requires: composer require phpmailer/phpmailer
 */

namespace InternHub;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $config;
    private $mail;
    
    public function __construct($configPath = null) {
        if ($configPath === null) {
            $configPath = __DIR__ . '/email_config.php';
        }
        
        if (!file_exists($configPath)) {
            throw new Exception('Email configuration file not found');
        }
        
        $this->config = require $configPath;
        $this->initMailer();
    }
    
    private function initMailer() {
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['smtp']['host'];
            $this->mail->Port = $this->config['smtp']['port'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config['smtp']['username'];
            $this->mail->Password = $this->config['smtp']['password'];
            $this->mail->SMTPSecure = $this->config['smtp']['encryption'];
            
            // Sender
            $this->mail->setFrom($this->config['from']['address'], $this->config['from']['name']);
        } catch (Exception $e) {
            throw new Exception('PHPMailer initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send password reset email
     * 
     * @param string $recipientEmail User's email
     * @param string $recipientName User's name
     * @param string $resetCode 6-digit reset code
     * @param string $resetLink Full URL to reset page
     * @return bool
     */
    public function sendPasswordResetEmail($recipientEmail, $recipientName, $resetCode, $resetLink) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            
            $this->mail->addAddress($recipientEmail, $recipientName);
            
            // Subject
            $this->mail->Subject = 'Password Reset Request - InternHub';
            
            // HTML body
            $htmlBody = $this->getPasswordResetTemplate($recipientName, $resetCode, $resetLink);
            $this->mail->msgHTML($htmlBody);
            
            // Plain text alternative
            $textBody = "Password Reset Request\n\n";
            $textBody .= "Hello {$recipientName},\n\n";
            $textBody .= "You requested a password reset for your InternHub account.\n\n";
            $textBody .= "Your reset code is: {$resetCode}\n";
            $textBody .= "This code will expire in 60 minutes.\n\n";
            $textBody .= "Or visit: {$resetLink}\n\n";
            $textBody .= "If you did not request this, please ignore this email.\n";
            $this->mail->AltBody = $textBody;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Password reset email failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password changed confirmation email
     */
    public function sendPasswordChangedEmail($recipientEmail, $recipientName) {
        try {
            $this->mail->clearAddresses();
            
            $this->mail->addAddress($recipientEmail, $recipientName);
            $this->mail->Subject = 'Password Changed Successfully - InternHub';
            
            $htmlBody = $this->getPasswordChangedTemplate($recipientName);
            $this->mail->msgHTML($htmlBody);
            
            $textBody = "Password Changed Successfully\n\n";
            $textBody .= "Hello {$recipientName},\n\n";
            $textBody .= "Your InternHub password has been successfully changed.\n\n";
            $textBody .= "If you did not make this change, please contact support immediately.\n";
            $this->mail->AltBody = $textBody;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Password changed email failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send account welcome email (for new users)
     */
    public function sendWelcomeEmail($recipientEmail, $recipientName, $role) {
        try {
            $this->mail->clearAddresses();
            
            $this->mail->addAddress($recipientEmail, $recipientName);
            $this->mail->Subject = 'Welcome to InternHub';
            
            $htmlBody = $this->getWelcomeTemplate($recipientName, $role);
            $this->mail->msgHTML($htmlBody);
            
            $textBody = "Welcome to InternHub\n\n";
            $textBody .= "Hello {$recipientName},\n\n";
            $textBody .= "Your {$role} account has been created.\n";
            $textBody .= "You will need to reset your initial password on first login.\n";
            $this->mail->AltBody = $textBody;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log('Welcome email failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get password reset email HTML template
     */
    private function getPasswordResetTemplate($name, $code, $link) {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9fafb; }
        .code-box { 
            background-color: #e5e7eb; 
            padding: 15px; 
            border-radius: 5px; 
            text-align: center; 
            margin: 20px 0;
            font-size: 24px;
            letter-spacing: 2px;
            font-weight: bold;
        }
        .footer { background-color: #111827; color: #9ca3af; padding: 15px; text-align: center; font-size: 12px; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$name}</strong>,</p>
            
            <p>You requested a password reset for your InternHub account. Your reset code is:</p>
            
            <div class="code-box">{$code}</div>
            
            <p>This code is valid for <strong>60 minutes</strong>.</p>
            
            <p>Or click the link below to reset your password:</p>
            <p><a href="{$link}" style="background-color: #2563eb; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; text-decoration: none;">Reset Password</a></p>
            
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
            
            <p style="color: #6b7280; font-size: 12px;">If you did not request this password reset, please ignore this email. Your account remains secure.</p>
        </div>
        <div class="footer">
            <p>© 2026 InternHub — ECL Escola de Comércio de Lisboa</p>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    private function getPasswordChangedTemplate($name) {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #10b981; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9fafb; }
        .footer { background-color: #111827; color: #9ca3af; padding: 15px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Changed Successfully</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$name}</strong>,</p>
            
            <p>Your InternHub password has been successfully changed.</p>
            
            <p>If you did not make this change or have any concerns about your account security, please contact support immediately.</p>
            
            <p>Your new password is now active and you can log in with it.</p>
        </div>
        <div class="footer">
            <p>© 2026 InternHub — ECL Escola de Comércio de Lisboa</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    private function getWelcomeTemplate($name, $role) {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f9fafb; }
        .footer { background-color: #111827; color: #9ca3af; padding: 15px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to InternHub</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{$name}</strong>,</p>
            
            <p>Welcome to InternHub! Your {$role} account has been successfully created.</p>
            
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>Log in with your email and initial password</li>
                <li>You will be prompted to create a new secure password</li>
                <li>Start tracking your internship hours and progress</li>
            </ol>
            
            <p>If you have any questions, please contact your coordinator or supervisor.</p>
        </div>
        <div class="footer">
            <p>© 2026 InternHub — ECL Escola de Comércio de Lisboa</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
?>
