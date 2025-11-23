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

function add_flash(array &$target, string $message): void
{
    $target[] = $message;
}
