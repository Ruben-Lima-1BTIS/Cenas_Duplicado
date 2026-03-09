<?php
/**
 * Email Configuration for InternHub
 * 
 * Configure your email settings here for password reset functionality
 */

return [
    // SMTP Configuration
    'smtp' => [
        'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('MAIL_PORT') ?: 587,
        'username' => getenv('MAIL_USERNAME') ?: 'your-email@gmail.com',
        'password' => getenv('MAIL_PASSWORD') ?: 'your-app-password',
        'encryption' => 'tls', // 'tls', 'ssl', or null
    ],
    
    // Email Sender Settings
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@internhub.local',
        'name' => getenv('MAIL_FROM_NAME') ?: 'InternHub',
    ],
    
    // Email Settings
    'settings' => [
        'reset_timeout_minutes' => 60, // Token expiration time
        'max_reset_attempts' => 3, // Max requests per hour
        'include_admin_on_reset' => false, // CC admin on reset emails
    ],
];
?>
