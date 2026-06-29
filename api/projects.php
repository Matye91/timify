<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_user_id();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = db()->prepare('SELECT id, name, color, created_at FROM projects WHERE user_id = ? ORDER BY name');
    $stmt->execute([$userId]);
    json_response(['projects' => $stmt->fetchAll()]);
}

$data = read_json();

if ($method === 'POST') {
    $name = trim((string) ($data['name'] ?? ''));
    $color = trim((string) ($data['color'] ?? '#2563eb'));

    if ($name === '') {
        json_response(['error' => 'Project name is required.'], 422);
    }

    validate_length($name, 'Project name', 120);

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        json_response(['error' => 'Project color must be a hex color.'], 422);
    }

    $stmt = db()->prepare('INSERT INTO projects (user_id, name, color) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $name, $color]);

    json_response(['project' => [
        'id' => (int) db()->lastInsertId(),
        'name' => $name,
        'color' => $color,
    ]], 201);
}

if ($method === 'PATCH') {
    $id = (int) ($data['id'] ?? 0);
    $name = trim((string) ($data['name'] ?? ''));
    $color = trim((string) ($data['color'] ?? '#2563eb'));

    if ($id <= 0 || $name === '') {
        json_response(['error' => 'Project id and name are required.'], 422);
    }

    validate_length($name, 'Project name', 120);

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        json_response(['error' => 'Project color must be a hex color.'], 422);
    }

    $stmt = db()->prepare('UPDATE projects SET name = ?, color = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$name, $color, $id, $userId]);

    json_response(['ok' => $stmt->rowCount() > 0]);
}

if ($method === 'DELETE') {
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Project id is required.'], 422);
    }

    $stmt = db()->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Method not allowed.'], 405);
