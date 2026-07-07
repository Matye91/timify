<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$userId = require_user_id();
ensure_method('GET');

function parse_report_datetime(?string $value, string $field): string
{
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        json_response(['error' => sprintf('%s must be a valid date and time.', $field)], 422);
    }

    return $date->format('Y-m-d H:i:s');
}

$weekStart = parse_report_datetime($_GET['start'] ?? null, 'Start');
$weekEnd = parse_report_datetime($_GET['end'] ?? null, 'End');

if (strtotime($weekEnd) <= strtotime($weekStart)) {
    json_response(['error' => 'End must be after start.'], 422);
}

$stmt = db()->prepare(
    'SELECT p.id, p.name, p.color,
            COALESCE(SUM(GREATEST(0, TIMESTAMPDIFF(
                SECOND,
                GREATEST(te.started_at, ?),
                LEAST(COALESCE(te.ended_at, UTC_TIMESTAMP()), ?)
            ))), 0) AS seconds
     FROM projects p
     LEFT JOIN time_entries te ON te.project_id = p.id
        AND te.user_id = p.user_id
        AND te.started_at < ?
        AND COALESCE(te.ended_at, UTC_TIMESTAMP()) > ?
     WHERE p.user_id = ?
     GROUP BY p.id, p.name, p.color
     ORDER BY seconds DESC, p.name'
);
$stmt->execute([$weekStart, $weekEnd, $weekEnd, $weekStart, $userId]);

json_response([
    'start' => $weekStart,
    'end' => $weekEnd,
    'projects' => $stmt->fetchAll(),
]);
