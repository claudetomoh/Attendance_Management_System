<?php
require_once __DIR__ . '/config.php';

$users = [
    [
        'name' => 'Dr. Osafo-Maafo',
        'email' => 'faculty@example.com',
        'password' => password_hash('Faculty123', PASSWORD_DEFAULT),
        'role' => 'faculty',
    ],
    [
        'name' => 'Claude Tomoh',
        'email' => 'student@example.com',
        'password' => password_hash('Student123', PASSWORD_DEFAULT),
        'role' => 'student',
    ],
    [
        'name' => 'Lisa Assistant',
        'email' => 'intern@example.com',
        'password' => password_hash('Intern123', PASSWORD_DEFAULT),
        'role' => 'intern',
    ],
];

$insertUser = $pdo->prepare(
    'INSERT INTO ' . TABLE_USERS . ' (name, email, password_hash, role) VALUES (:name, :email, :hash, :role)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = VALUES(role)'
);

foreach ($users as $user) {
    $insertUser->execute([
        'name' => $user['name'],
        'email' => $user['email'],
        'hash' => $user['password'],
        'role' => $user['role'],
    ]);
}

$facultyUser = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = :email AND role = "faculty"');
$facultyUser->execute(['email' => 'faculty@example.com']);
$facultyId = $facultyUser->fetchColumn();

if ($facultyId) {
    $insertCourse = $pdo->prepare(
        'INSERT INTO ' . TABLE_COURSES . ' (title, description, instructor_id) VALUES (:title, :description, :instructor_id)
         ON DUPLICATE KEY UPDATE description = VALUES(description)'
    );
    $insertCourse->execute([
        'title' => 'Introduction to Web Tech',
        'description' => 'HTML, CSS, and the fundamentals of delivering classroom-ready content.',
        'instructor_id' => $facultyId,
    ]);

    $insertCourse->execute([
        'title' => 'Database Systems Essentials',
        'description' => 'Hands-on SQLite queries, joins, and schema design for classrooms.',
        'instructor_id' => $facultyId,
    ]);

    $course = $pdo->prepare('SELECT id FROM ' . TABLE_COURSES . ' WHERE title = :title AND instructor_id = :instructor_id');
    $course->execute([
        'title' => 'Introduction to Web Tech',
        'instructor_id' => $facultyId,
    ]);
    $courseId = $course->fetchColumn();

    $student = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = :email AND role = "student"');
    $student->execute(['email' => 'student@example.com']);
    $studentId = $student->fetchColumn();

    $internStmt = $pdo->prepare('SELECT id FROM ' . TABLE_USERS . ' WHERE email = :email AND role = "intern"');
    $internStmt->execute(['email' => 'intern@example.com']);
    $internId = $internStmt->fetchColumn();

    if ($courseId && $studentId) {
        $insertRequest = $pdo->prepare(
            'INSERT INTO ' . TABLE_JOIN_REQUESTS . ' (course_id, student_id, status) VALUES (:course_id, :student_id, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP'
        );
        $insertRequest->execute([
            'course_id' => $courseId,
            'student_id' => $studentId,
            'status' => 'approved',
        ]);

        $sessionCheck = $pdo->prepare(
            'SELECT id FROM ' . TABLE_COURSE_SESSIONS . ' WHERE course_id = :course_id AND title = :title'
        );
        $sessionTitle = 'Kickoff Session';
        $sessionCheck->execute([
            'course_id' => $courseId,
            'title' => $sessionTitle,
        ]);

        if (!$sessionCheck->fetchColumn()) {
            $sessionInsert = $pdo->prepare(
                'INSERT INTO ' . TABLE_COURSE_SESSIONS . ' (course_id, title, session_date, access_code, created_by)
                 VALUES (:course_id, :title, :session_date, :code, :created_by)'
            );
            $sessionInsert->execute([
                'course_id' => $courseId,
                'title' => $sessionTitle,
                'session_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'code' => 'WELCOME',
                'created_by' => $facultyId,
            ]);

            $sessionId = (int) $pdo->lastInsertId();

            if ($sessionId) {
                $attendance = $pdo->prepare(
                    'INSERT INTO ' . TABLE_ATTENDANCE_RECORDS . ' (session_id, student_id, status, method)
                     VALUES (:session_id, :student_id, "present", "staff")
                     ON DUPLICATE KEY UPDATE status = "present", method = "staff", marked_at = CURRENT_TIMESTAMP'
                );
                $attendance->execute([
                    'session_id' => $sessionId,
                    'student_id' => $studentId,
                ]);
            }

            if ($internId) {
                $assignAssistant = $pdo->prepare(
                    'INSERT INTO ' . TABLE_COURSE_STAFF . ' (course_id, staff_id, role) VALUES (:course_id, :staff_id, "intern")
                     ON DUPLICATE KEY UPDATE role = VALUES(role), created_at = CURRENT_TIMESTAMP'
                );
                $assignAssistant->execute([
                    'course_id' => $courseId,
                    'staff_id' => $internId,
                ]);
            }
        }
    }
}

echo "Seed complete. Use faculty@example.com / Faculty123, intern@example.com / Intern123, or student@example.com / Student123 to log in.\n";
?>