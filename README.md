# SmartRegister

SmartRegister is a lightweight attendance management system built with PHP and SQLite. It supports three types of users (faculty, interns, students) and streamlines course creation, enrollment requests, and attendance tracking. Faculty and assigned interns can create sessions, generate check-in codes, and mark attendance. Students can request courses, join sessions with a one-time code, and view their daily and overall attendance summaries.

## Features

- **Authentication**: Secure registration/login with password hashing (`password_hash`, `password_verify`).
- **Course Management**: Faculty and interns create courses, approve/reject student join requests, and add assistants to share teaching duties.
- **Attendance Sessions**:
  - Create sessions with unique access codes.
  - Close sessions to stop self check-ins.
  - Manual attendance overrides for special cases.
- **Student Self Check-In**: Students enter codes to mark themselves present, with safeguards to ensure they belong to the course.
- **Reporting**:
  - Faculty/interns view per-session stats (present vs. enrolled).
  - Students see daily sessions plus per-course attendance totals.

## Getting Started

### Requirements
- PHP 8+
- SQLite (bundled with PHP)
- XAMPP or any LAMP stack

### Installation
1. Clone or download this repository into your web root (`htdocs` for XAMPP).
2. Ensure the `data` directory is writable (PHP will create it automatically).
3. Run the seed script to populate demo users:
   ```bash
   php seed.php
   ```
4. Start Apache (if using XAMPP) and navigate to `http://localhost/Attendance_Management_System/Attendance_Management_System/login.php`.

### Demo Accounts
- Faculty: `faculty@example.com` / `Faculty123`
- Intern: `intern@example.com` / `Intern123`
- Student: `student@example.com` / `Student123`

## Code Structure
```
attendance_manage.php     # Attendance hub for faculty/interns
config.php                # SQLite connection + schema setup
faculty.php               # Teaching hub (course creation/requests/assistants)
functions.php             # Shared helpers (auth guards, sanitization, etc.)
student_dashboard.php     # Student course requests, attendance check-in
login.php / register.php  # Auth pages
style.css                 # Global styles
seed.php                  # Seeds demo data and sample attendance session
```

## Key Tables
- `users`: role-based accounts (faculty, intern, student)
- `courses`: owned by faculty, optionally assisted by interns (`course_staff`)
- `join_requests`: student enrollment status per course
- `course_sessions`: attendance sessions with access codes
- `attendance_records`: per-session student attendance entries

## Extending
- Add more roles or permissions by expanding the `require_roles` helper.
- Integrate email or SMS notifications when sessions open/close.
- Replace SQLite with MySQL by updating `config.php` to use the appropriate PDO DSN and credentials.

## Contributing
Pull requests are welcome! Please lint PHP files (`php -l filename.php`) before committing and ensure new features include basic seed data for quick testing.
