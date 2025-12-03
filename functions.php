<?php

function sanitize(string $value): string
{
    return trim($value);
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function old(string $key): string
{
    return escape($_POST[$key] ?? '');
}

function require_login(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }

    return $_SESSION['user'];
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        header('Location: dashboard.php');
        exit;
    }

    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        header('Location: dashboard.php');
        exit;
    }

    return $user;
}

function add_flash(array &$target, string $message): void
{
    $target[] = $message;
}

function generate_session_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function is_student_enrolled(PDO $pdo, int $studentId, int $courseId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM ' . TABLE_JOIN_REQUESTS . ' WHERE student_id = :student_id AND course_id = :course_id AND status = "approved"'
    );
    $stmt->execute([
        'student_id' => $studentId,
        'course_id' => $courseId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function get_accessible_course_ids(PDO $pdo, array $user): array
{
    if ($user['role'] === 'faculty') {
        $stmt = $pdo->prepare('SELECT id FROM ' . TABLE_COURSES . ' WHERE instructor_id = :id');
    } else {
        $stmt = $pdo->prepare(
            'SELECT course_id FROM ' . TABLE_COURSE_STAFF . ' WHERE staff_id = :id'
        );
    }

    $stmt->execute(['id' => $user['id']]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function user_has_course_access(PDO $pdo, array $user, int $courseId): bool
{
    if ($user['role'] === 'faculty') {
        $stmt = $pdo->prepare('SELECT 1 FROM ' . TABLE_COURSES . ' WHERE id = :course_id AND instructor_id = :user_id');
    } else {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM ' . TABLE_COURSE_STAFF . ' WHERE course_id = :course_id AND staff_id = :user_id'
        );
    }

    $stmt->execute([
        'course_id' => $courseId,
        'user_id' => $user['id'],
    ]);

    return (bool) $stmt->fetchColumn();
}
