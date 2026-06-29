<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_user_id();
$method = $_SERVER['REQUEST_METHOD'];

function stop_running_entry(int $userId): void
{
    $stmt = db()->prepare('UPDATE time_entries SET ended_at = UTC_TIMESTAMP() WHERE user_id = ? AND ended_at IS NULL');
    $stmt->execute([$userId]);
}

function parse_utc_datetime(?string $value, string $field, bool $required = true): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        if ($required) {
            json_response(['error' => sprintf('%s is required.', $field)], 422);
        }

        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        json_response(['error' => sprintf('%s must be a valid date and time.', $field)], 422);
    }

    return $date->format('Y-m-d H:i:s');
}

if ($method === 'GET') {
    $stmt = db()->prepare(
        'SELECT te.id, te.project_id, te.description, te.started_at, te.ended_at,
                p.name AS project_name, p.color AS project_color,
                TIMESTAMPDIFF(SECOND, te.started_at, COALESCE(te.ended_at, UTC_TIMESTAMP())) AS seconds
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

    $stmt = db()->prepare('INSERT INTO time_entries (user_id, project_id, description, started_at) VALUES (?, ?, ?, UTC_TIMESTAMP())');
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
        $stmt = db()->prepare('UPDATE time_entries SET ended_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND ended_at IS NULL');
        $stmt->execute([$id, $userId]);
        json_response(['ok' => true]);
    }

    if ($action === 'update') {
        $projectId = (int) ($data['project_id'] ?? 0);
        $description = trim((string) ($data['description'] ?? ''));
        $startedAt = parse_utc_datetime($data['started_at'] ?? null, 'Start time');
        $endedAt = parse_utc_datetime($data['ended_at'] ?? null, 'End time', false);

        if ($projectId <= 0) {
            json_response(['error' => 'Please select a project.'], 422);
        }

        validate_length($description, 'Description', 500);

        if ($endedAt !== null && strtotime($endedAt) < strtotime($startedAt)) {
            json_response(['error' => 'End time must be after start time.'], 422);
        }

        $projectStmt = db()->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
        $projectStmt->execute([$projectId, $userId]);
        if (!$projectStmt->fetch()) {
            json_response(['error' => 'Project not found.'], 404);
        }

        $stmt = db()->prepare(
            'UPDATE time_entries
             SET project_id = ?, description = ?, started_at = ?, ended_at = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$projectId, $description !== '' ? $description : null, $startedAt, $endedAt, $id, $userId]);
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
