# SmartRegister

SmartRegister is a lightweight attendance management system built with PHP and MySQL. It supports three types of users (faculty, interns, students) and streamlines course creation, enrollment requests, and attendance tracking. Faculty and assigned interns can create sessions, generate check-in codes, and mark attendance. Students can request courses, join sessions with a one-time code, and view their daily and overall attendance summaries.

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
- MySQL 5.7+ / MariaDB 10+
- XAMPP or any standard LAMP stack

### Installation
1. Clone or download this repository into your web root (`htdocs` for XAMPP).
2. Run the seed script to populate demo users:
   ```bash
   php seed.php
   ```
3. Start Apache (if using XAMPP) and navigate to `http://localhost/Attendance_Management_System/Attendance_Management_System/login.php`.

### Database configuration
`config.php` now reads connection details from the following environment variables (with sensible local defaults):

| Variable        | Purpose                      | Default                     |
|-----------------|------------------------------|-----------------------------|
| `AMS_DB_HOST`   | Database hostname             | `localhost`                |
| `AMS_DB_PORT`   | Database port                 | `3306`                     |
| `AMS_DB_NAME`   | Database/schema name          | `webtech_2025A_tomoh_ikfingeh` |
| `AMS_DB_USER`   | Username                      | `tomoh.ikfingeh`           |
| `AMS_DB_PASS`   | Password                      | *(configured)*             |

You can also use the conventional `DB_HOST`, `DB_PORT`, `DB_NAME`/`DB_DATABASE`, `DB_USER`/`DB_USERNAME`, and `DB_PASS`/`DB_PASSWORD` variable names if your hosting provider already exposes them.

Example `.htaccess` snippet for shared hosting:

```
SetEnv AMS_DB_HOST localhost
SetEnv AMS_DB_PORT 3306
SetEnv AMS_DB_NAME your_database
SetEnv AMS_DB_USER your_username
SetEnv AMS_DB_PASS your_password
```

After updating the variables (or editing the defaults in `config.php` directly), reload the site and the tables will be created automatically under the `AMS_*` prefixes (`AMS_users`, `AMS_courses`, etc.).

### Demo Accounts
- Faculty: `faculty@example.com` / `Faculty123`
- Intern: `intern@example.com` / `Intern123`
- Student: `student@example.com` / `Student123`

### Live server
The project is deployed at: <http://169.239.251.102:341/~tomoh.ikfingeh/Attendance_Management_System/>

## Code Structure
```
attendance_manage.php     # Attendance hub for faculty/interns
config.php                # MySQL connection + schema setup
faculty.php               # Teaching hub (course creation/requests/assistants)
functions.php             # Shared helpers (auth guards, sanitization, etc.)
student_dashboard.php     # Student course requests, attendance check-in
login.php / register.php  # Auth pages
style.css                 # Global styles
seed.php                  # Seeds demo data and sample attendance session
```

## Key Tables
- `AMS_users`: role-based accounts (faculty, intern, student)
- `AMS_courses`: owned by faculty, optionally assisted by interns (`AMS_course_staff`)
- `AMS_join_requests`: student enrollment status per course
- `AMS_course_sessions`: attendance sessions with access codes
- `AMS_attendance_records`: per-session student attendance entries

## Extending
- Configure an alternate MySQL database by exporting environment variables (see above) or editing the defaults inside `config.php`.
- Add more roles or permissions by expanding the `require_roles` helper.
- Integrate email or SMS notifications when sessions open/close.

## Contributing
Pull requests are welcome! Please lint PHP files (`php -l filename.php`) before committing and ensure new features include basic seed data for quick testing.
