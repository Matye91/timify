<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_user_id();
ensure_method('GET');

$stmt = db()->prepare(
    'SELECT p.id, p.name, p.color,
            COALESCE(SUM(TIMESTAMPDIFF(SECOND, te.started_at, COALESCE(te.ended_at, UTC_TIMESTAMP()))), 0) AS seconds
     FROM projects p
     LEFT JOIN time_entries te ON te.project_id = p.id AND te.user_id = p.user_id
     WHERE p.user_id = ?
     GROUP BY p.id, p.name, p.color
     ORDER BY seconds DESC, p.name'
);
$stmt->execute([$userId]);

json_response(['projects' => $stmt->fetchAll()]);
