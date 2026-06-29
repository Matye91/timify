<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_user_id();
$method = $_SERVER['REQUEST_METHOD'];

function stop_running_entry(int $userId): void
{
    $stmt = db()->prepare('UPDATE time_entries SET ended_at = NOW() WHERE user_id = ? AND ended_at IS NULL');
    $stmt->execute([$userId]);
}

if ($method === 'GET') {
    $stmt = db()->prepare(
        'SELECT te.id, te.project_id, te.description, te.started_at, te.ended_at,
                p.name AS project_name, p.color AS project_color,
                TIMESTAMPDIFF(SECOND, te.started_at, COALESCE(te.ended_at, NOW())) AS seconds
         FROM time_entries te
         JOIN projects p ON p.id = te.project_id
         WHERE te.user_id = ?
         ORDER BY te.started_at DESC
         LIMIT 200'
    );
    $stmt->execute([$userId]);
    json_response(['entries' => $stmt->fetchAll()]);
}

$data = read_json();

if ($method === 'POST') {
    $projectId = (int) ($data['project_id'] ?? 0);
    $description = trim((string) ($data['description'] ?? ''));

    if ($projectId <= 0) {
        json_response(['error' => 'Please select a project.'], 422);
    }

    validate_length($description, 'Description', 500);

    $stmt = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
    $stmt->execute([$projectId, $userId]);
    if (!$stmt->fetch()) {
        json_response(['error' => 'Project not found.'], 404);
    }

    stop_running_entry($userId);

    $stmt = db()->prepare('INSERT INTO time_entries (user_id, project_id, description, started_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $projectId, $description !== '' ? $description : null]);
    json_response(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'PATCH') {
    $action = (string) ($data['action'] ?? '');
    $id = (int) ($data['id'] ?? 0);

    if ($id <= 0) {
        json_response(['error' => 'Entry id is required.'], 422);
    }

    if ($action === 'stop') {
        $stmt = db()->prepare('UPDATE time_entries SET ended_at = NOW() WHERE id = ? AND user_id = ? AND ended_at IS NULL');
        $stmt->execute([$id, $userId]);
        json_response(['ok' => true]);
    }

    if ($action === 'update') {
        $projectId = (int) ($data['project_id'] ?? 0);
        $description = trim((string) ($data['description'] ?? ''));

        if ($projectId <= 0) {
            json_response(['error' => 'Please select a project.'], 422);
        }

        validate_length($description, 'Description', 500);

        $stmt = db()->prepare(
            'UPDATE time_entries te
             JOIN projects p ON p.id = ? AND p.user_id = te.user_id
             SET te.project_id = ?, te.description = ?
             WHERE te.id = ? AND te.user_id = ?'
        );
        $stmt->execute([$projectId, $projectId, $description !== '' ? $description : null, $id, $userId]);
        json_response(['ok' => $stmt->rowCount() > 0]);
    }
}

if ($method === 'DELETE') {
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Entry id is required.'], 422);
    }

    $stmt = db()->prepare('DELETE FROM time_entries WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Method not allowed.'], 405);
