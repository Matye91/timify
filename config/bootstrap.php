<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

start_app_session();

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['error' => 'Invalid JSON body.'], 400);
    }

    return $data;
}

function require_user_id(): int
{
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Authentication required.'], 401);
    }

    return (int) $_SESSION['user_id'];
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function ensure_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_response(['error' => 'Method not allowed.'], 405);
    }
}

function validate_length(string $value, string $field, int $max): void
{
    if (mb_strlen($value) > $max) {
        json_response(['error' => sprintf('%s may not exceed %d characters.', $field, $max)], 422);
    }
}
