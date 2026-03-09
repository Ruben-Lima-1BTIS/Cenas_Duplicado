# InternHub Quick Reference Guide

## Authentication Flows

### 1. Login
```
User → auth.php
  ↓
Search email in all user tables
  ↓
Verify password hash
  ↓
Set session variables:
  - user_id
  - role (student/supervisor/coordinator)
  - email
  - first_login flag
  - table name
  ↓
If first_login=1 → change_password.php
Else → role-specific dashboard
```

### 2. First-Time Password Change
```
change_password.php
  ↓
Check session user_id & first_login
  ↓
Validate new password (8+ chars, mixed case, numbers, symbols)
  ↓
Hash password → Update database
  ↓
Set first_login = 0
  ↓
Redirect to dashboard
```

### 3. Forgot Password
```
forgot_password.php (Step 1)
  ↓
Enter email → Search all user tables
  ↓
Generate 6-digit code
Hash code → Store in password_reset_tokens
  ↓
Session stores: reset_email, reset_role, reset_user_id
  ↓
Show Step 2 form

forgot_password.php (Step 2)
  ↓
Enter code + new password
  ↓
Verify code against hashed token
Check token not used & not expired
  ↓
Hash new password → Update user table
  ↓
Mark token as used
Clear session
  ↓
Show success message & link to login
```

### 4. Logout
```
logout.php
  ↓
session_destroy()
  ↓
Redirect to auth.php
```

---

## Database Tables

### Users
- `students` (id, name, email, password_hash, class_id, first_login)
- `supervisors` (id, name, email, password_hash, company_id, first_login)
- `coordinators` (id, name, email, password_hash, first_login)

### Password Recovery
- `password_reset_tokens` (id, user_id, user_role, reset_code, token_hash, is_used, expires_at)

### Internships
- `internships` (id, company_id, title, start_date, end_date, total_hours_required)
- `student_internships` (id, student_id, internship_id, assigned_at)
- `supervisor_internships` (id, supervisor_id, internship_id, assigned_at)

### Work Tracking
- `hours` (id, student_id, internship_id, date, start_time, end_time, duration_hours, status, supervisor_reviewed_by, supervisor_comment)
- `reports` (id, student_id, title, file_path, status, feedback, created_at)

### Messaging
- `conversations` (id, user1_role, user1_id, user2_role, user2_id, convo_key)
- `messages` (id, conversation_id, sender_role, sender_id, body, created_at, read_at)

---

## Key Files

### Authentication
- `overall_actions/auth.php` - Login page & handler
- `overall_actions/forgot_password.php` - Password recovery
- `overall_actions/change_password.php` - First-time password change
- `overall_actions/logout.php` - Session termination

### Utilities
- `overall_actions/messages.php` - Messaging interface
- `overall_actions/settings.php` - User settings & password update
- `dont_touch_kinda_stuff/db.php` - Database connection

### Student
- `student_actions/dashboard.php` - Hours & progress charts
- `student_actions/log_hours.php` - Record internship hours
- `student_actions/submit-reports.php` - Weekly report submission

### Supervisor
- `supervisor_actions/dashboard_supervisor.php` - Overview
- `supervisor_actions/approve_hours.php` - Approve/reject hours
- `supervisor_actions/review_reports.php` - Review student reports
- `supervisor_actions/student_progress.php` - Monitor progress

### Coordinator
- `coordinator_actions/dashboard_coordinator.php` - Overview
- `coordinator_actions/review_reports.php` - Review all reports
- `coordinator_actions/student_progress.php` - Class progress

### Admin
- `dont_touch_kinda_stuff/user_creation.php` - Create users & internships

---

## Session Variables

When user logs in, these variables are set:
```php
$_SESSION['user_id']      // Numeric ID of user
$_SESSION['role']         // 'student', 'supervisor', 'coordinator'
$_SESSION['email']        // User email
$_SESSION['first_login']  // 1 or 0
$_SESSION['table']        // Database table name (students, supervisors, etc)
```

When password reset started:
```php
$_SESSION['reset_email']      // Email being reset
$_SESSION['reset_role']       // Role of user
$_SESSION['reset_user_id']    // ID of user
$_SESSION['reset_code_sent']  // (Demo only) Shows code for testing
```

---

## Password Requirements

- Minimum 8 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- At least one symbol (!@#$%^&*()_+-)

---

## Common Query Patterns

### Get user by email
```php
$stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Get internship assigned to student
```php
$stmt = $conn->prepare("
    SELECT i.* FROM internships i
    JOIN student_internships si ON si.internship_id = i.id
    WHERE si.student_id = ?
");
$stmt->execute([$student_id]);
```

### Get hours pending approval
```php
$stmt = $conn->prepare("
    SELECT h.* FROM hours h
    JOIN internships i ON h.internship_id = i.id
    WHERE i.id IN (....) AND h.status = 'pending'
");
```

---

## Error Handling

All files use PDOException try-catch:
```php
try {
    // Database operation
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
```

All forms display user-friendly error/success messages in colored boxes.

---

## Security Best Practices Used

✅ Prepared statements (prevents SQL injection)  
✅ Password hashing with PASSWORD_DEFAULT  
✅ Session-based authentication  
✅ Role-based access control  
✅ Input validation & sanitization  
✅ HTML output escaping  
✅ Token expiration (60 min)  
✅ One-time use tokens  

---

## Debugging Tips

1. **Check session variables**: `var_dump($_SESSION);`
2. **Database connection**: Test `db.php` directly
3. **Login issues**: Verify email exists in correct table
4. **Access denied**: Check `$_SESSION['role']` matches required role
5. **Password hash**: Use `password_verify($_POST['password'], $hash)`

---

## Deployment Checklist

- [ ] Create database with updated schema
- [ ] Configure MySQL user & password in db.php
- [ ] Set up email service for password reset
- [ ] Remove demo code showing reset codes
- [ ] Configure SMTP/mail() settings
- [ ] Test password reset with real email
- [ ] Run backup before deployment
- [ ] Monitor error logs
- [ ] Test all three user roles
- [ ] Verify file permissions (uploads folder writable)
