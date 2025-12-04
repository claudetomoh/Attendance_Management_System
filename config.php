<?php
require_once __DIR__ . '/env.php';

const TABLE_USERS = '`AMS_users`';
const TABLE_COURSES = '`AMS_courses`';
const TABLE_JOIN_REQUESTS = '`AMS_join_requests`';
const TABLE_COURSE_SESSIONS = '`AMS_course_sessions`';
const TABLE_ATTENDANCE_RECORDS = '`AMS_attendance_records`';
const TABLE_COURSE_STAFF = '`AMS_course_staff`';

try {
    $dbHost = ams_env('AMS_DB_HOST', ams_env('DB_HOST', 'localhost'));
    $dbPort = (int) ams_env('AMS_DB_PORT', ams_env('DB_PORT', 3306));
    $dbName = ams_env('AMS_DB_NAME', ams_env('DB_NAME', ams_env('DB_DATABASE', 'webtech_2025A_tomoh_ikfingeh')));
    $dbUser = ams_env('AMS_DB_USER', ams_env('DB_USER', ams_env('DB_USERNAME', 'tomoh.ikfingeh')));
    $dbPass = ams_env('AMS_DB_PASS', ams_env('DB_PASS', ams_env('DB_PASSWORD', 'STCL@ude20@?')));

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(sprintf(
        'CREATE TABLE IF NOT EXISTS %s (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM("faculty", "student", "intern") NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        TABLE_USERS
    ));

    $pdo->exec(sprintf(
        'CREATE TABLE IF NOT EXISTS %s (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title TEXT NOT NULL,
            description TEXT,
            instructor_id INTEGER NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (instructor_id) REFERENCES %s(id) ON DELETE CASCADE
        )',
        TABLE_COURSES,
        TABLE_USERS
    ));

    $pdo->exec(sprintf(
        'CREATE TABLE IF NOT EXISTS %s (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "pending",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES %s(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES %s(id) ON DELETE CASCADE,
            UNIQUE(course_id, student_id)
        )',
        TABLE_JOIN_REQUESTS,
        TABLE_COURSES,
        TABLE_USERS
    ));

    $pdo->exec(sprintf(
        'CREATE TABLE IF NOT EXISTS %s (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            session_date DATETIME NOT NULL,
            access_code TEXT NOT NULL UNIQUE,
            status ENUM("open", "closed") NOT NULL DEFAULT "open",
            created_by INTEGER NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES %s(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES %s(id) ON DELETE CASCADE
        )',
        TABLE_COURSE_SESSIONS,
        TABLE_COURSES,
        TABLE_USERS
    ));

    $pdo->exec(sprintf(
        'CREATE TABLE IF NOT EXISTS %s (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            marked_by INTEGER,
            status ENUM("present", "absent", "excused") NOT NULL DEFAULT "present",
            method ENUM("self", "staff") NOT NULL DEFAULT "self",
            marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES %s(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES %s(id) ON DELETE CASCADE,
            FOREIGN KEY (marked_by) REFERENCES %s(id) ON DELETE SET NULL,
            UNIQUE(session_id, student_id)
        )',
        TABLE_ATTENDANCE_RECORDS,
        TABLE_COURSE_SESSIONS,
        TABLE_USERS,
        TABLE_USERS
    ));

    $pdo->exec(sprintf(
        'CREATE TABLE IF NOT EXISTS %s (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INTEGER NOT NULL,
            staff_id INTEGER NOT NULL,
            role TEXT NOT NULL DEFAULT "assistant",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES %s(id) ON DELETE CASCADE,
            FOREIGN KEY (staff_id) REFERENCES %s(id) ON DELETE CASCADE,
            UNIQUE(course_id, staff_id)
        )',
        TABLE_COURSE_STAFF,
        TABLE_COURSES,
        TABLE_USERS
    ));
} catch (PDOException $e) {
    die('Database initialization failed: ' . htmlspecialchars($e->getMessage()));
}
