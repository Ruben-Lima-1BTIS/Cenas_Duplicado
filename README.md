# InternHub - Internship Management System

A comprehensive web-based platform for managing student internships, built with PHP, MySQL, Tailwind CSS, and JavaScript.

## Features

### For Students
- Log internship hours with automatic validation
- Submit weekly reports
- Track progress towards required hours
- View approval status for hours and reports
- Dashboard with visual analytics

### For Supervisors
- Approve or reject student hours
- Review and provide feedback on reports
- Monitor student progress
- View detailed analytics

### For Coordinators
- Oversee multiple classes
- Review all student reports
- Monitor class-wide progress
- Identify at-risk students

### General Features
- Secure authentication with password change on first login
- Role-based access control
- Real-time progress tracking
- Responsive design
- Settings page for account management

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML, Tailwind CSS, JavaScript
- **Charts**: Chart.js

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Web browser

### Setup Steps

1. **Clone or download the project**
   ```bash
   cd /path/to/your/webserver
   ```

2. **Configure Database Connection**
   - Edit `dont_touch_kinda_stuff/db.php`
   - Update database credentials:
     ```php
     $host = "localhost";
     $dbname = "internhub_nova";
     $user = "root";
     $password = "";
     ```

3. **Import Database**
   - Create database:
     ```sql
     CREATE DATABASE internhub_nova CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
     ```
   - Import schema:
     ```bash
     mysql -u root -p internhub_nova < dump/sql_file/internhub_nova.sql
     ```
   - Or use phpMyAdmin to import `dump/sql_dumps/internhub_dump.sql`

4. **Create Users (HR Function)**
   - Access `dont_touch_kinda_stuff/user_creation.php`
   - Create entities in this order:
     1. Companies
     2. Coordinators
     3. Classes
     4. Internships
     5. Supervisors
     6. Students
   - Assign supervisors to internships
   - Assign students to internships

5. **Access the Application**
   - Open `index.php` in your browser
   - Login with created credentials
   - Default password will prompt for change on first login

## Directory Structure

```
internhub/
├── coordinator_actions/     # Coordinator pages
│   ├── dashboard_coordinator.php
│   ├── review_reports.php
│   └── student_progress.php
├── supervisor_actions/      # Supervisor pages
│   ├── dashboard_supervisor.php
│   ├── approve_hours.php
│   ├── review_reports.php
│   └── student_progress.php
├── student_actions/         # Student pages
│   ├── dashboard.php
│   ├── log_hours.php
│   ├── submit-reports.php
│   └── uploads/            # Student report files
├── overall_actions/         # Shared pages
│   ├── auth.php
│   ├── change_password.php
│   ├── forgot_password.php
│   ├── logout.php
│   ├── messages.php
│   └── settings.php
├── dont_touch_kinda_stuff/  # Core files
│   ├── db.php
│   └── user_creation.php
├── dump/                    # Database files
│   ├── sql_file/
│   └── sql_dumps/
└── index.php               # Landing page
```

## User Roles

### Student
- **Login**: Email assigned by HR
- **Features**: Log hours, submit reports, view progress
- **Dashboard**: Hours tracking, report status

### Supervisor
- **Login**: Email assigned by HR
- **Features**: Approve hours, review reports, monitor interns
- **Dashboard**: Pending approvals, student progress

### Coordinator
- **Login**: Email assigned by HR
- **Features**: Oversee classes, review all reports, monitor progress
- **Dashboard**: Class overview, at-risk students

## Key Features Explained

### Hour Logging
- Students log daily hours with start/end times
- Automatic calculation with lunch break deduction
- Validation: weekdays only, within internship dates
- Requires supervisor approval
- Cannot edit approved entries

### Report Submission
- Weekly report uploads (PDF, DOC, DOCX, TXT)
- Automatic week calculation based on internship dates
- Download submitted reports
- Feedback system

### Progress Tracking
- Visual charts and progress bars
- Hours: required vs approved vs pending
- Report submission tracking
- Status indicators (On Track, At Risk)

### Security
- Password hashing
- Session management
- Role-based access control
- SQL injection prevention
- XSS protection

## Database Schema Highlights

### Main Tables
- `students`, `supervisors`, `coordinators` - User accounts
- `classes` - Academic classes
- `companies` - Internship providers
- `internships` - Internship programs
- `hours` - Student hour logs
- `reports` - Weekly reports
- `conversations`, `messages` - Messaging (placeholder)

### Key Relationships
- Students → Classes → Coordinators
- Students → Internships → Companies
- Supervisors → Companies
- Supervisors → Internships (many-to-many)

## Troubleshooting

### Database Connection Issues
- Verify credentials in `dont_touch_kinda_stuff/db.php`
- Check MySQL service is running
- Ensure database exists

### Login Issues
- Confirm user created via `user_creation.php`
- Check email/password are correct
- Verify role assignment

### File Upload Issues
- Ensure `student_actions/uploads/reports/` is writable
- Check PHP `upload_max_filesize` and `post_max_size`
- Verify file extensions allowed

### Hours Not Calculating
- Check internship dates match log dates
- Verify `lunch_break_minutes` is set
- Ensure student assigned to internship

## Future Enhancements

- Full messaging system implementation
- Email notifications
- Calendar view for hours
- Export functionality (PDF, Excel)
- Mobile app
- Advanced analytics

## Support

For issues or questions:
- Check database schema in `dump/sql_file/`
- Review user creation in `dont_touch_kinda_stuff/user_creation.php`
- Verify all paths in navigation links

## License

Educational project for ECL - Escola de Comércio de Lisboa

## Credits

**Developer**: Ruben Alexandre Nobre Lima
**Project**: Final Evaluation Project
**Institution**: ECL – Escola de Comércio de Lisboa
**Year**: 2025
