<?php
/**
 * CSRF Token Manager
 * 
 * Handles generation and validation of CSRF tokens
 * for form protection
 */

class CSRFToken {
    private static $token_length = 32;
    
    /**
     * Generate or retrieve CSRF token
     * @param string $key Session key for storing token
     * @return string The CSRF token
     */
    public static function generate($key = 'csrf_token') {
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(self::$token_length));
        }
        return $_SESSION[$key];
    }
    
    /**
     * Get hidden input field with CSRF token
     * @param string $key Session key for storing token
     * @return string HTML input field
     */
    public static function field($key = 'csrf_token') {
        $token = self::generate($key);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF token from POST data
     * @param string $key Session key for stored token
     * @param array $data POST data (defaults to $_POST)
     * @return bool True if token is valid
     */
    public static function validate($key = 'csrf_token', $data = null) {
        if ($data === null) {
            $data = $_POST;
        }
        
        if (!isset($_SESSION[$key]) || !isset($data['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION[$key], $data['csrf_token']);
    }
    
    /**
     * Validate and regenerate token (use after form submission)
     * @param string $key Session key
     * @return bool
     */
    public static function validateAndRegenerate($key = 'csrf_token') {
        $valid = self::validate($key);
        unset($_SESSION[$key]); // Clear old token
        self::generate($key); // Generate new one
        return $valid;
    }
}
?>
