<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function require_admin(): void
{
    if (empty($_SESSION['admin'])) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function current_admin(PDO $pdo): ?array
{
    if (empty($_SESSION['admin'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id_admin, nom, email FROM admins WHERE id_admin = ?');
    $stmt->execute([$_SESSION['admin']]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Session expiree. Merci de recharger la page.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit();
}

function log_action(PDO $pdo, string $action): void
{
    $stmt = $pdo->prepare('INSERT INTO logs (id_admin, action) VALUES (?, ?)');
    $stmt->execute([$_SESSION['admin'] ?? null, $action]);
}

function demande_status_label(?string $status): string
{
    return [
        'en_attente' => 'En attente',
        'acceptee' => 'Acceptee',
        'rejetee' => 'Rejetee',
    ][$status] ?? 'Inconnu';
}

function demande_status_class(?string $status): string
{
    return [
        'en_attente' => 'warning',
        'acceptee' => 'success',
        'rejetee' => 'danger',
    ][$status] ?? 'secondary';
}

function rdv_status_label(?string $status): string
{
    return [
        'programme' => 'Programme',
        'effectue' => 'Effectue',
        'annule' => 'Annule',
    ][$status] ?? 'Inconnu';
}

function rdv_status_class(?string $status): string
{
    return [
        'programme' => 'primary',
        'effectue' => 'success',
        'annule' => 'secondary',
    ][$status] ?? 'secondary';
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

function format_date(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d/m/Y', strtotime($value));
}
