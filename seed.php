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
];

$insertUser = $pdo->prepare(
    'INSERT OR IGNORE INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, :role)'
);

foreach ($users as $user) {
    $insertUser->execute([
        'name' => $user['name'],
        'email' => $user['email'],
        'hash' => $user['password'],
        'role' => $user['role'],
    ]);
}

$facultyUser = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = "faculty"');
$facultyUser->execute(['email' => 'faculty@example.com']);
$facultyId = $facultyUser->fetchColumn();

if ($facultyId) {
    $insertCourse = $pdo->prepare(
        'INSERT OR IGNORE INTO courses (title, description, instructor_id) VALUES (:title, :description, :instructor_id)'
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

    $course = $pdo->prepare('SELECT id FROM courses WHERE title = :title AND instructor_id = :instructor_id');
    $course->execute([
        'title' => 'Introduction to Web Tech',
        'instructor_id' => $facultyId,
    ]);
    $courseId = $course->fetchColumn();

    $student = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = "student"');
    $student->execute(['email' => 'student@example.com']);
    $studentId = $student->fetchColumn();

    if ($courseId && $studentId) {
        $insertRequest = $pdo->prepare(
            'INSERT OR IGNORE INTO join_requests (course_id, student_id, status) VALUES (:course_id, :student_id, :status)'
        );
        $insertRequest->execute([
            'course_id' => $courseId,
            'student_id' => $studentId,
            'status' => 'pending',
        ]);
    }
}

echo "Seed complete. Use faculty@example.com / Faculty123 or student@example.com / Student123 to log in.\n";
